<?php
declare(strict_types=1);

/** Construye el contexto de Plantia y delega el orden híbrido al motor Python. */
final class AdaptiveRecommendationService
{
    public function __construct(private PDO $db) {}

    /** @param array<int,array<string,mixed>> $courses */
    public function rank(int $userId, array $courses): array
    {
        if (!$courses || !$this->hasProfile($userId)) return $courses;
        $profile=$this->profile($userId);
        $document = ['profile'=>$profile, 'courses'=>$this->withTags($courses, $profile), 'events'=>$this->events($userId), 'rules'=>$this->rules()];
        $this->annotateBehaviorMatches($document['courses'], $document['events']);
        $decision = $this->callPython($document);
        if (!$decision || empty($decision['recommendations'])) return $courses;
        $positions=[];
        foreach ($decision['recommendations'] as $position=>$recommendation) $positions[(int)$recommendation['course_id']]=[$position,$recommendation];
        foreach ($courses as &$course) {
            if (!isset($positions[(int)$course['id']])) continue;
            [, $recommendation]=$positions[(int)$course['id']];
            $course['recommendation_score']=$recommendation['score'];
            $course['recommendation_reason']=$recommendation['reasons'][0] ?? 'Recomendado según tu perfil';
            $course['_adaptive_position']=$positions[(int)$course['id']][0];
        }
        unset($course);
        usort($courses, static fn(array $a, array $b): int => ($a['_adaptive_position'] ?? PHP_INT_MAX) <=> ($b['_adaptive_position'] ?? PHP_INT_MAX));
        foreach ($courses as &$course) unset($course['_adaptive_position']);
        return $courses;
    }

    public function record(int $userId, string $type, ?int $courseId = null, array $payload = []): void
    {
        $event=$this->db->prepare('INSERT INTO adaptive_events(user_id,course_id,event_type,payload,occurred_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP)');
        $event->execute([$userId,$courseId,$type,json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    private function hasProfile(int $userId): bool { $q=$this->db->prepare('SELECT 1 FROM user_profiles WHERE user_id=?'); $q->execute([$userId]); return (bool)$q->fetchColumn(); }

    private function profile(int $userId): array
    {
        $q=$this->db->prepare('SELECT knowledge_level,has_pets,weak_connection FROM user_profiles WHERE user_id=?'); $q->execute([$userId]);
        $profile=$q->fetch(PDO::FETCH_ASSOC) ?: [];
        $tags=$this->db->prepare('SELECT t.code FROM user_tags ut JOIN tags t ON t.id=ut.tag_id WHERE ut.user_id=?'); $tags->execute([$userId]);
        $plants=$this->db->prepare('SELECT pt.code,pt.ecosystem,pt.toxicity_level,f.code family_code FROM user_plants up JOIN plant_taxa pt ON pt.id=up.plant_taxon_id LEFT JOIN plant_taxa f ON f.id=pt.parent_id WHERE up.user_id=?');
        $plants->execute([$userId]);
        $plantRows=$plants->fetchAll(PDO::FETCH_ASSOC);
        $ecosystems=array_count_values(array_filter(array_column($plantRows,'ecosystem')));
        return ['id'=>$userId, ...$profile, 'tags'=>$tags->fetchAll(PDO::FETCH_COLUMN), 'plants'=>array_column($plantRows,'code'), 'plant_families'=>array_values(array_unique(array_filter(array_column($plantRows,'family_code')))), 'user_plant_family'=>!empty($plantRows), 'registered_plant_toxicity'=>array_values(array_unique(array_filter(array_column($plantRows,'toxicity_level')))), 'plant_ecosystem_count'=>$ecosystems ? max($ecosystems) : 0, 'dominant_plant_ecosystem'=>$ecosystems ? array_key_first(array_filter($ecosystems, static fn(int $count): bool => $count===max($ecosystems))) : null];
    }

    private function withTags(array $courses, array $profile): array
    {
        $ids=array_column($courses,'id'); $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->db->prepare("SELECT ct.course_id,t.code FROM course_tags ct JOIN tags t ON t.id=ct.tag_id WHERE ct.course_id IN ($marks)"); $q->execute($ids);
        $tags=[]; foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) $tags[(int)$row['course_id']][]=$row['code'];
        $plants=$this->db->prepare("SELECT cpt.course_id,pt.code,pt.ecosystem,f.code family_code FROM course_plant_taxa cpt JOIN plant_taxa pt ON pt.id=cpt.plant_taxon_id LEFT JOIN plant_taxa f ON f.id=pt.parent_id WHERE cpt.course_id IN ($marks)");
        $plants->execute($ids);
        $coursePlants=[]; foreach($plants->fetchAll(PDO::FETCH_ASSOC) as $row) $coursePlants[(int)$row['course_id']][]=$row;
        $required=$this->db->prepare("SELECT ct.course_id,t.code FROM course_tags ct JOIN tags t ON t.id=ct.tag_id WHERE ct.is_required=1 AND ct.course_id IN ($marks)");
        $required->execute($ids); $requiredTags=[]; foreach($required->fetchAll(PDO::FETCH_ASSOC) as $row) $requiredTags[(int)$row['course_id']][]=$row['code'];
        foreach($courses as &$course) {
            $courseId=(int)$course['id'];
            $course['tags']=$tags[$courseId] ?? [];
            $plantsForCourse=$coursePlants[$courseId] ?? [];
            $course['matches_registered_plant']=boolval(array_intersect($profile['plants'],array_column($plantsForCourse,'code')));
            $course['matches_plant_family']=boolval(array_intersect($profile['plant_families'],array_filter(array_column($plantsForCourse,'family_code'))));
            $courseEcosystems=array_unique(array_filter(array_column($plantsForCourse,'ecosystem')));
            $course['matches_plant_ecosystem']=in_array($profile['dominant_plant_ecosystem'],$courseEcosystems,true);
            $course['matches_dominant_ecosystem']=$course['matches_plant_ecosystem'];
            $course['profile_course_match_count']=count(array_intersect($profile['tags'],$course['tags']));
            $course['course_required_tag_missing']=boolval(array_diff($requiredTags[$courseId] ?? [],$profile['tags']));
            $course['course_score']=$course['final_score'] ?? null;
        }
        return $courses;
    }

    private function events(int $userId): array
    {
        $q=$this->db->prepare('SELECT course_id,event_type,occurred_at FROM adaptive_events WHERE user_id=? ORDER BY occurred_at DESC LIMIT 200'); $q->execute([$userId]);
        return array_map(static fn(array $e): array => ['course_id'=>(int)$e['course_id'],'type'=>$e['event_type'],'occurred_at'=>$e['occurred_at']],$q->fetchAll(PDO::FETCH_ASSOC));
    }

    private function rules(): array
    {
        $rules=$this->db->query('SELECT id,code,name,priority FROM recommendation_rules WHERE is_active=1 ORDER BY priority DESC')->fetchAll(PDO::FETCH_ASSOC);
        $condition=$this->db->prepare('SELECT attribute,operator,value FROM recommendation_rule_conditions WHERE rule_id=?');
        $action=$this->db->prepare('SELECT a.action,t.code target_tag,a.score,a.message,a.action_data FROM recommendation_rule_actions a LEFT JOIN tags t ON t.id=a.target_tag_id WHERE a.rule_id=?');
        foreach($rules as &$rule) {
            $condition->execute([$rule['id']]); $rule['conditions']=array_map(static fn(array $c): array => ['attribute'=>$c['attribute'],'operator'=>$c['operator'],'value'=>json_decode($c['value'],true) ?? []],$condition->fetchAll(PDO::FETCH_ASSOC));
            $action->execute([$rule['id']]); $rule['actions']=array_map(static fn(array $a): array => ['action'=>$a['action'],'target_tag'=>$a['target_tag'],'score'=>(float)$a['score'],'message'=>$a['message'],'data'=>json_decode($a['action_data'] ?? '{}',true) ?? []],$action->fetchAll(PDO::FETCH_ASSOC));
        }
        return $rules;
    }

    private function annotateBehaviorMatches(array &$courses, array $events): void
    {
        $byId=[]; foreach($courses as $course) $byId[(int)$course['id']]=$course['tags'];
        $opened=[]; $dismissed=[]; $enrolled=[]; $topicCounts=[];
        foreach($events as $event) {
            $eventTags=$byId[$event['course_id']] ?? [];
            if($event['type']==='opened') { $opened=array_merge($opened,$eventTags); foreach($eventTags as $tag) $topicCounts[$tag]=($topicCounts[$tag] ?? 0)+1; }
            if($event['type']==='dismissed') $dismissed=array_merge($dismissed,$eventTags);
            if($event['type']==='enrolled') $enrolled=array_merge($enrolled,$eventTags);
        }
        foreach($courses as &$course) {
            $course['similar_to_opened_course']=boolval(array_intersect($course['tags'],$opened));
            $course['same_or_similar_to_dismissed_course']=boolval(array_intersect($course['tags'],$dismissed));
            $course['complementary_to_enrolled_course']=boolval(array_diff($course['tags'],$enrolled));
            $course['topic_interaction_count']=max([0,...array_map(static fn(string $tag): int => $topicCounts[$tag] ?? 0,$course['tags'])]);
            $course['course_matches_repeated_topic']=$course['topic_interaction_count'] >= 2;
        }
    }

    private function callPython(array $document): ?array
    {
        $python=getenv('PYTHON_BIN') ?: 'python';
        $script=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'adaptive_engine.py';
        if (!is_file($script) || !function_exists('proc_open')) return null;
        $model=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'adaptive-model.json';
        $process=proc_open([$python,$script,'recommend','--input','-','--model',$model,'--limit',(string)count($document['courses'])], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) return null;
        fwrite($pipes[0],json_encode($document,JSON_UNESCAPED_UNICODE)); fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
        if (proc_close($process)!==0) return null;
        $result=json_decode($out,true); return is_array($result) ? $result : null;
    }
}
