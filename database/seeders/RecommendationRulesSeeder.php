<?php
declare(strict_types=1);

/** Seeds the recommendation policy from “Reglas Plantia.pdf”. */
final class RecommendationRulesSeeder
{
    public static function run(PDO $db): int
    {
        $tags = $db->query('SELECT code,id FROM tags')->fetchAll(PDO::FETCH_KEY_PAIR);
        $rules = array_merge(
            self::tagRules('space', ['context-pot'=>['topic-container-gardening','topic-substrates'], 'context-garden'=>['topic-soil-life','topic-watering'], 'context-balcony'=>['topic-urban-gardening','topic-container-gardening'], 'context-small-outdoor'=>['topic-vertical-gardening','topic-container-gardening'], 'context-small-indoor'=>['interest-indoor'], 'context-large-indoor'=>['interest-foliage'], 'context-vertical'=>['topic-vertical-gardening']]),
            self::tagRules('light_environment', ['light-low'=>['ecosystem-shade','ecosystem-semi-shade'], 'light-sun'=>['ecosystem-sun'], 'light-indirect'=>['interest-indoor'], 'light-afternoon-sun'=>['topic-climate-protection'], 'environment-humid'=>['ecosystem-humid','ecosystem-tropical'], 'environment-dry'=>['ecosystem-dry','interest-succulents-cacti'], 'environment-temperature-swings'=>['topic-climate-protection']]),
            self::tagRules('goals', ['goal-learn-basics'=>['interest-basic-botany'], 'goal-care'=>['topic-watering','topic-substrates','topic-fertilization','topic-pests','topic-diagnosis'], 'goal-propagate'=>['topic-propagation','topic-layering','topic-grafting','topic-seed-starting'], 'goal-design-garden'=>['topic-garden-design'], 'goal-grow-food'=>['interest-orchard','interest-edibles','topic-composting'], 'goal-control-pests'=>['topic-pests','topic-diseases']]),
            self::tagRules('interests', ['interest-indoor'=>['topic-container-gardening','interest-foliage'], 'interest-succulents-cacti'=>['topic-substrates','topic-watering','ecosystem-sun'], 'interest-orchids'=>['ecosystem-humid','topic-watering','topic-substrates']]),
            self::tagRules('regions', ['warm-dry'=>['ecosystem-dry','topic-water-saving'], 'warm-humid'=>['topic-drainage','topic-diseases'], 'cold-highland'=>['topic-climate-protection'], 'semiarid'=>['topic-water-saving','ecosystem-dry'] ], 'region'),
            self::specialRules()
        );
        foreach ($rules as $rule) { self::store($db, $rule, $tags); }
        return count($rules);
    }

    /** @return array<int,array<string,mixed>> */
    private static function tagRules(string $domain, array $map, string $attribute = 'user_tag'): array
    {
        $rules=[];
        foreach ($map as $source=>$targets) foreach ($targets as $target) $rules[]=['code'=>"{$domain}.{$source}.{$target}",'name'=>"{$source} prioriza {$target}",'priority'=>50,'conditions'=>[[$attribute,'equals',[$source]]],'actions'=>[['add_score',$target,20,['course_must_have_tag'=>$target]]]];
        return $rules;
    }

    /** @return array<int,array<string,mixed>> */
    private static function specialRules(): array
    {
        return [
            ['code'=>'plants.species-care','name'=>'Cuidado específico de la planta registrada','priority'=>90,'conditions'=>[['user_plant','has_plant',[]]],'actions'=>[['add_score',null,35,['course_matches_registered_plant'=>true]],['add_score',null,20,['course_matches_plant_ecosystem'=>true]]]],
            ['code'=>'plants.family-care','name'=>'Cuidado y reproducción de la familia botánica','priority'=>80,'conditions'=>[['user_plant_family','has_plant',[]]],'actions'=>[['add_score',null,25,['course_matches_plant_family'=>true]],['add_score','topic-propagation',15,['course_matches_plant_family'=>true]],['add_score','topic-pruning',10,['course_matches_plant_family'=>true,'when_applicable'=>true]],['add_score','topic-pests',15,['course_matches_plant_family'=>true]]]],
            ['code'=>'plants.multiple-ecosystem','name'=>'Manejo integral de ecosistemas repetidos','priority'=>70,'conditions'=>[['plant_ecosystem_count','gte',[2]]],'actions'=>[['add_score',null,25,['course_matches_dominant_ecosystem'=>true]],['diversify',null,0,['alternate_plant_families'=>true]]]],
            ['code'=>'plants.new-registration','name'=>'Cuidados iniciales al registrar una planta','priority'=>85,'conditions'=>[['event','equals',['plant_registered']]],'actions'=>[['refresh_recommendations',null,0,[]],['add_score','topic-watering',20,['course_matches_registered_plant_or_family'=>true]],['add_score','topic-substrates',20,['course_matches_registered_plant_or_family'=>true]],['add_score','topic-propagation',15,['course_matches_registered_plant_or_family'=>true]]]],
            ['code'=>'time.low','name'=>'Poco tiempo: cursos cortos y sencillos','priority'=>75,'conditions'=>[['user_tag','equals',['time-low']]],'actions'=>[['add_score',null,18,['max_estimated_minutes'=>90]],['subtract_score',null,-20,['min_estimated_minutes'=>240]],['prefer_shorter',null,0,[]],['add_score','content-short-modules',15,[]]]],
            ['code'=>'time.medium','name'=>'Tiempo moderado: duración media y práctica','priority'=>50,'conditions'=>[['user_tag','equals',['time-medium']]],'actions'=>[['add_score',null,15,['duration_range_minutes'=>[90,240]]],['add_score','content-guided-practice',15,[]]]],
            ['code'=>'time.high','name'=>'Mucho tiempo: cursos profundos y proyectos','priority'=>50,'conditions'=>[['user_tag','equals',['time-high']]],'actions'=>[['add_score',null,15,['min_estimated_minutes'=>240]]]],
            ['code'=>'time.abandonment','name'=>'Abandono por falta de tiempo','priority'=>90,'conditions'=>[['abandonment_reason','equals',['lack_of_time']]],'actions'=>[['subtract_score',null,-25,['min_estimated_minutes'=>240]],['add_score','content-short-modules',20,[]],['prefer_shorter',null,0,[]]]],
            ['code'=>'safety.pets','name'=>'Seguridad para mascotas','priority'=>100,'conditions'=>[['profile_field','equals',['has_pets',true]]],'actions'=>[['show_warning',null,0,['message_key'=>'pet_safe_space']],['add_score','safety-pet-safe',25,[]],['show_safe_alternative',null,0,['when_course_has_toxic_species'=>true]]]],
            ['code'=>'safety.toxic-plant','name'=>'Advertencia por planta tóxica registrada','priority'=>110,'conditions'=>[['profile_field','equals',['has_pets',true]],['registered_plant_toxicity','equals',['toxic','caution']]],'actions'=>[['show_warning',null,0,['message_key'=>'registered_plant_safety','before_enrollment'=>true]]]],
            ['code'=>'connectivity.weak','name'=>'Conexión débil: texto y bajo consumo','priority'=>90,'conditions'=>[['profile_field','equals',['weak_connection',true]]],'actions'=>[['prefer_text_content','content-text',20,[]],['add_score','content-low-bandwidth',25,[]],['subtract_score',null,-20,['video_or_heavy_image_majority'=>true]]]],
            ['code'=>'level.beginner','name'=>'Nivel principiante','priority'=>80,'conditions'=>[['profile_field','equals',['knowledge_level','beginner']]],'actions'=>[['add_score',null,25,['course_level'=>'beginner']],['subtract_score',null,-20,['course_level'=>'advanced']]]],
            ['code'=>'level.intermediate','name'=>'Nivel intermedio','priority'=>80,'conditions'=>[['profile_field','equals',['knowledge_level','intermediate']]],'actions'=>[['add_score',null,25,['course_level'=>'intermediate']]]],
            ['code'=>'level.advanced','name'=>'Nivel avanzado','priority'=>80,'conditions'=>[['profile_field','equals',['knowledge_level','advanced']]],'actions'=>[['add_score',null,25,['course_level'=>'advanced']]]],
            ['code'=>'learning.completion','name'=>'Progresión después de completar','priority'=>85,'conditions'=>[['course_status','equals',['completed']]],'actions'=>[['add_score',null,25,['relationship_in'=>['continuation','deepening','practical_application']]],['promote_next_level',null,0,[]]]],
            ['code'=>'learning.low-performance','name'=>'Refuerzo por desempeño bajo','priority'=>85,'conditions'=>[['course_score','lt',[60]]],'actions'=>[['add_score',null,25,['relationship'=>'reinforcement','same_topic'=>true]],['exclude_course',null,0,['higher_level_same_topic'=>true]]]],
            ['code'=>'behavior.open','name'=>'Interés al abrir recomendación','priority'=>45,'conditions'=>[['recommendation_event','equals',['opened']]],'actions'=>[['add_score',null,8,['similar_to_opened_course'=>true]]]],
            ['code'=>'behavior.enroll','name'=>'Complementar al inscribirse','priority'=>60,'conditions'=>[['recommendation_event','equals',['enrolled']]],'actions'=>[['subtract_score',null,-12,['very_similar_to_enrolled_course'=>true]],['add_score',null,12,['complementary_to_enrolled_course'=>true]]]],
            ['code'=>'behavior.no-interest','name'=>'Abandono por falta de interés','priority'=>75,'conditions'=>[['abandonment_reason','equals',['lack_of_interest']]],'actions'=>[['subtract_score',null,-25,['same_topic_as_abandoned_course'=>true]],['diversify',null,0,['prefer_other_user_interests'=>true]]]],
            ['code'=>'behavior.dismiss','name'=>'Descartar recomendación','priority'=>75,'conditions'=>[['recommendation_event','equals',['dismissed']]],'actions'=>[['subtract_score',null,-25,['same_or_similar_to_dismissed_course'=>true]]]],
            ['code'=>'behavior.repeated-interest','name'=>'Interacción repetida con una temática','priority'=>55,'conditions'=>[['topic_interaction_count','gte',[2]]],'actions'=>[['add_score',null,15,['course_matches_repeated_topic'=>true]]]],
            ['code'=>'behavior.inactive','name'=>'Recomendación sin interacción','priority'=>30,'conditions'=>[['recommendation_age_days','gte',[14]],['recommendation_event_count','equals',[0]]],'actions'=>[['subtract_score',null,-5,['current_recommendation'=>true]]]],
            ['code'=>'priority.matching','name'=>'Premiar coincidencias múltiples','priority'=>95,'conditions'=>[['profile_course_match_count','gte',[3]]],'actions'=>[['add_score',null,30,['current_course'=>true]]]],
            ['code'=>'priority.required-condition','name'=>'Excluir condiciones requeridas incumplidas','priority'=>105,'conditions'=>[['course_required_tag_missing','equals',[true]]],'actions'=>[['exclude_course',null,0,['current_course'=>true]]]],
            ['code'=>'priority.diversity','name'=>'Diversidad temática y desempates','priority'=>40,'conditions'=>[['same_topic_recommendation_count','gte',[3]]],'actions'=>[['diversify',null,0,['include_related_other_topics'=>true]],['tie_break',null,0,['low_time'=>'shorter_duration','all_users'=>'closest_level']]]],
            ['code'=>'priority.fallback','name'=>'Sin coincidencias suficientes','priority'=>10,'conditions'=>[['profile_course_match_count','lt',[1]]],'actions'=>[['add_score','interest-basic-botany',20,['course_level'=>'beginner']],['request_profile_data',null,0,['fields'=>['space','light','time','region','plants']]]]],
        ];
    }

    private static function store(PDO $db, array $rule, array $tags): void
    {
        $now=date('Y-m-d H:i:s');
        $q=$db->prepare('INSERT INTO recommendation_rules (code,name,priority,is_active,description,created_at,updated_at) VALUES (?,?,?,1,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),priority=VALUES(priority),description=VALUES(description),is_active=1,updated_at=VALUES(updated_at)');
        $q->execute([$rule['code'],$rule['name'],$rule['priority'],'Regla sembrada desde el documento Reglas Plantia.',$now,$now]);
        $id=(int)$db->prepare('SELECT id FROM recommendation_rules WHERE code=?')->execute([$rule['code']]);
        $s=$db->prepare('SELECT id FROM recommendation_rules WHERE code=?'); $s->execute([$rule['code']]); $ruleId=(int)$s->fetchColumn();
        $db->prepare('DELETE FROM recommendation_rule_conditions WHERE rule_id=?')->execute([$ruleId]); $db->prepare('DELETE FROM recommendation_rule_actions WHERE rule_id=?')->execute([$ruleId]);
        $condition=$db->prepare('INSERT INTO recommendation_rule_conditions (rule_id,attribute,operator,value) VALUES (?,?,?,?)'); foreach($rule['conditions'] as [$a,$o,$v]) $condition->execute([$ruleId,$a,$o,json_encode($v,JSON_UNESCAPED_UNICODE)]);
        $action=$db->prepare('INSERT INTO recommendation_rule_actions (rule_id,action,target_tag_id,score,message,action_data) VALUES (?,?,?,?,?,?)'); foreach($rule['actions'] as [$a,$tag,$score,$data]) $action->execute([$ruleId,$a,$tag===null?null:($tags[$tag]??null),$score,null,json_encode($data,JSON_UNESCAPED_UNICODE)]);
    }
}
