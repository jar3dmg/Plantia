<?php
declare(strict_types=1);

/** Catálogo botánico mexicano ampliado y relaciones de recomendación por curso. */
final class ComprehensivePlantCatalogSeeder
{
    public static function run(PDO $db): array
    {
        $families = self::families();
        $family = $db->prepare("INSERT INTO plant_taxa(parent_id,taxon_type,code,scientific_name,common_name,is_active) VALUES(NULL,'family',?,?,?,1) ON DUPLICATE KEY UPDATE scientific_name=VALUES(scientific_name),common_name=VALUES(common_name),is_active=1");
        foreach ($families as $code => $data) $family->execute([$code, $data[0], $data[1]]);
        $familyIds = $db->query("SELECT code,id FROM plant_taxa WHERE taxon_type='family'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $species = $db->prepare("INSERT INTO plant_taxa(parent_id,taxon_type,code,scientific_name,common_name,ecosystem,toxicity_level,pet_safety_warning,is_active) VALUES(?,'species',?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id),scientific_name=VALUES(scientific_name),common_name=VALUES(common_name),ecosystem=VALUES(ecosystem),toxicity_level=VALUES(toxicity_level),pet_safety_warning=VALUES(pet_safety_warning),is_active=1");
        $catalog = self::catalog();
        foreach ($catalog as $row) {
            [$familyCode,$scientific,$common,$ecosystem,$toxicity,$courses] = $row;
            $code = 'species-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $scientific))), '-');
            $warning = $toxicity === 'toxic' ? 'Mantén esta especie fuera del alcance de mascotas; consulta a un veterinario ante una ingestión.' : ($toxicity === 'caution' ? 'Evita la ingestión por mascotas y supervisa su ubicación.' : null);
            $species->execute([(int)$familyIds[$familyCode], trim($code,'-'), $scientific, $common, $ecosystem, $toxicity, $warning]);
        }
        self::relateCourses($db, $catalog);
        return ['families'=>count($families),'species'=>count($catalog),'relations'=>self::relationCount($catalog)];
    }

    private static function relateCourses(PDO $db, array $catalog): void
    {
        $courseIds = $db->query("SELECT slug,id FROM courses WHERE slug IN ('botanica-desde-cero','plantas-felices-en-casa','tu-primer-huerto')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($courseIds) !== 3) throw new RuntimeException('Primero ejecuta LearningCoursesSeeder.');
        $taxa = $db->query("SELECT code,id FROM plant_taxa WHERE taxon_type='species'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $insert = $db->prepare('INSERT INTO course_plant_taxa(course_id,plant_taxon_id,weight) VALUES(?,?,?) ON DUPLICATE KEY UPDATE weight=VALUES(weight)');
        foreach ($catalog as $row) {
            [$family,$scientific,,,,$courses] = $row;
            $code='species-'.trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $scientific))),'-');
            foreach ($courses as $slug => $weight) $insert->execute([(int)$courseIds[$slug],(int)$taxa[$code],$weight]);
        }
        $tags = ['botanica-desde-cero'=>['topic-plant-identification','interest-basic-botany'],'plantas-felices-en-casa'=>['topic-watering','topic-drainage','topic-propagation','interest-indoor'],'tu-primer-huerto'=>['topic-container-gardening','topic-watering','topic-substrates','interest-edibles','interest-orchard']];
        $tag = $db->prepare('INSERT INTO course_tags(course_id,tag_id,weight,is_required) SELECT ?,id,80,0 FROM tags WHERE code=? ON DUPLICATE KEY UPDATE weight=VALUES(weight)');
        foreach ($tags as $slug=>$codes) foreach ($codes as $code) $tag->execute([(int)$courseIds[$slug],$code]);
    }

    private static function relationCount(array $catalog): int { return array_sum(array_map(fn($row)=>count($row[5]),$catalog)); }
    private static function families(): array { return [
        'family-araceae'=>['Araceae','Aráceas'],'family-asparagaceae'=>['Asparagaceae','Asparagáceas'],'family-cactaceae'=>['Cactaceae','Cactáceas'],'family-crassulaceae'=>['Crassulaceae','Crassuláceas'],'family-lamiaceae'=>['Lamiaceae','Lamiáceas'],'family-solanaceae'=>['Solanaceae','Solanáceas'],'family-asteraceae'=>['Asteraceae','Asteráceas'],'family-orchidaceae'=>['Orchidaceae','Orquídeas'],'family-apiaceae'=>['Apiaceae','Apiáceas'],'family-brassicaceae'=>['Brassicaceae','Brasicáceas'],'family-cucurbitaceae'=>['Cucurbitaceae','Cucurbitáceas'],'family-fabaceae'=>['Fabaceae','Leguminosas'],'family-rutaceae'=>['Rutaceae','Rutáceas'],'family-myrtaceae'=>['Myrtaceae','Mirtáceas'],'family-piperaceae'=>['Piperaceae','Piperáceas'],'family-polypodiaceae'=>['Polypodiaceae','Helechos verdaderos'],
    ]; }
    private static function catalog(): array
    {
        $B=['botanica-desde-cero'=>100]; $I=['botanica-desde-cero'=>60,'plantas-felices-en-casa'=>100]; $H=['botanica-desde-cero'=>60,'tu-primer-huerto'=>100];
        $rows=[]; $add=function(string $family,string $ecosystem,string $toxicity,array $courses,string $items) use (&$rows): void { foreach (explode(';',$items) as $item) { [$scientific,$common]=array_map('trim',explode('|',$item,2)); $rows[]=[$family,$scientific,$common,$ecosystem,$toxicity,$courses]; } };
        $add('family-araceae','indoor','toxic',$I,'Aglaonema commutatum|Aglaonema;Alocasia macrorrhizos|Oreja de elefante;Anthurium andraeanum|Anturio;Colocasia esculenta|Malanga;Dieffenbachia seguine|Diefembaquia;Epipremnum pinnatum|Pothos plateado;Monstera adansonii|Monstera adansonii;Monstera deliciosa|Costilla de Adán;Philodendron erubescens|Filodendro rojo;Philodendron hederaceum|Filodendro corazón;Rhaphidophora tetrasperma|Mini monstera;Spathiphyllum wallisii|Cuna de Moisés;Syngonium podophyllum|Singonio;Xanthosoma sagittifolium|Yautía;Zantedeschia aethiopica|Alcatraz');
        $add('family-asparagaceae','indoor','caution',$I,'Agave attenuata|Agave cola de zorro;Agave tequilana|Agave azul;Asparagus densiflorus|Helecho espárrago;Beaucarnea recurvata|Pata de elefante;Cordyline fruticosa|Cordiline;Dracaena fragrans|Tronco de Brasil;Dracaena marginata|Drácena marginata;Dracaena reflexa|Drácena;Dracaena trifasciata|Sansevieria;Haworthiopsis attenuata|Haworthia;Nolina recurvata|Pata de elefante;Ophiopogon japonicus|Pasto mondo;Sansevieria cylindrica|Sansevieria cilíndrica;Yucca elephantipes|Yuca pie de elefante;Yucca filamentosa|Yuca');
        $add('family-cactaceae','dry','safe',$I,'Astrophytum myriostigma|Bonete de obispo;Cereus repandus|Cactus peruano;Echinocactus grusonii|Asiento de suegra;Echinopsis chamaecereus|Cactus cacahuate;Ferocactus latispinus|Biznaga;Mammillaria elongata|Dedo de dama;Mammillaria spinosissima|Mammillaria;Myrtillocactus geometrizans|Garambullo;Opuntia ficus-indica|Nopal;Pachycereus marginatus|Órgano;Pachycereus pringlei|Cardón;Rhipsalis baccifera|Cactus muérdago;Schlumbergera truncata|Cactus de Navidad;Stenocereus thurberi|Pitaya;Selenicereus undatus|Pitahaya');
        $add('family-crassulaceae','dry','caution',$I,'Aeonium arboreum|Aeonio;Crassula ovata|Árbol de jade;Crassula perforata|Collar de botones;Echeveria agavoides|Echeveria;Echeveria elegans|Rosa de alabastro;Graptopetalum paraguayense|Planta fantasma;Kalanchoe blossfeldiana|Kalanchoe;Kalanchoe daigremontiana|Madre de miles;Kalanchoe thyrsiflora|Paleta;Sedum adolphii|Sedum dorado;Sedum morganianum|Cola de burro;Sedum rubrotinctum|Frijolitos;Sempervivum tectorum|Siempreviva;Tacitus bellus|Graptopetalum;Tylecodon paniculatus|Pata de elefante');
        $add('family-lamiaceae','sun','safe',$H,'Ocimum basilicum|Albahaca;Ocimum tenuiflorum|Albahaca sagrada;Mentha spicata|Menta;Mentha x piperita|Hierbabuena;Origanum vulgare|Orégano;Origanum majorana|Mejorana;Salvia rosmarinus|Romero;Salvia officinalis|Salvia;Thymus vulgaris|Tomillo;Lavandula angustifolia|Lavanda;Melissa officinalis|Toronjil;Nepeta cataria|Hierba gatera;Plectranthus amboinicus|Orégano cubano;Satureja montana|Ajedrea;Hyssopus officinalis|Hisopo');
        $add('family-solanaceae','sun','caution',$H,'Solanum lycopersicum|Jitomate;Solanum melongena|Berenjena;Capsicum annuum|Chile;Capsicum chinense|Chile habanero;Capsicum frutescens|Chile tabasco;Physalis philadelphica|Tomatillo;Physalis peruviana|Uchuva;Nicotiana alata|Tabaco ornamental;Petunia axillaris|Petunia;Datura stramonium|Toloache;Solanum tuberosum|Papa;Solanum muricatum|Pepino dulce;Cestrum nocturnum|Dama de noche;Brunfelsia pauciflora|Ayer hoy y mañana;Lycium barbarum|Goji');
        $add('family-asteraceae','sun','safe',$B,'Tagetes erecta|Cempasúchil;Tagetes patula|Clavel de moro;Dahlia pinnata|Dalia;Cosmos bipinnatus|Cosmos;Cosmos sulphureus|Mirasol;Zinnia elegans|Zinnia;Helianthus annuus|Girasol;Calendula officinalis|Caléndula;Gerbera jamesonii|Gerbera;Echinacea purpurea|Equinácea;Rudbeckia hirta|Rudbeckia;Coreopsis tinctoria|Coreopsis;Tithonia rotundifolia|Girasol mexicano;Artemisia absinthium|Ajenjo;Achillea millefolium|Milenrama');
        $add('family-orchidaceae','humid','safe',$I,'Phalaenopsis amabilis|Orquídea mariposa;Cattleya labiata|Cattleya;Dendrobium nobile|Dendrobio;Oncidium sphacelatum|Lluvia de oro;Cymbidium ensifolium|Cymbidium;Epidendrum radicans|Orquídea crucifijo;Laelia anceps|Laelia;Lycaste aromatica|Lycaste;Paphiopedilum insigne|Zapatilla de Venus;Vanda coerulea|Vanda;Brassavola nodosa|Dama de la noche;Miltonia spectabilis|Miltonia;Odontoglossum crispum|Odontogloso;Vanilla planifolia|Vainilla;Stanhopea tigrina|Torito');
        $add('family-apiaceae','sun','safe',$H,'Coriandrum sativum|Cilantro;Petroselinum crispum|Perejil;Apium graveolens|Apio;Daucus carota|Zanahoria;Foeniculum vulgare|Hinojo;Anethum graveolens|Eneldo;Cuminum cyminum|Comino;Pimpinella anisum|Anís;Angelica archangelica|Angélica;Carum carvi|Alcaravea;Levisticum officinale|Levístico;Pastinaca sativa|Chirivía;Ammi majus|Encaje de reina;Eryngium foetidum|Culantro;Bupleurum rotundifolium|Bupleuro');
        $add('family-brassicaceae','sun','safe',$H,'Brassica oleracea|Col;Brassica rapa|Nabo;Brassica juncea|Mostaza;Brassica nigra|Mostaza negra;Raphanus sativus|Rábano;Eruca vesicaria|Arúgula;Lepidium sativum|Berro;Sinapis alba|Mostaza blanca;Nasturtium officinale|Berro de agua;Matthiola incana|Alhelí;Alyssum maritimum|Aliso;Iberis sempervirens|Carraspique;Erysimum cheiri|Alhelí amarillo;Lunaria annua|Moneda del papa;Capsella bursa-pastoris|Bolsa de pastor');
        $add('family-cucurbitaceae','sun','safe',$H,'Cucurbita pepo|Calabacita;Cucurbita maxima|Calabaza;Cucurbita moschata|Calabaza de Castilla;Cucumis sativus|Pepino;Cucumis melo|Melón;Citrullus lanatus|Sandía;Sechium edule|Chayote;Lagenaria siceraria|Calabazo;Luffa aegyptiaca|Esponja vegetal;Momordica charantia|Cundeamor;Cucurbita argyrosperma|Calabaza pipiana;Benincasa hispida|Calabaza blanca;Cyclanthera pedata|Caigua;Coccinia grandis|Tindora;Trichosanthes cucumerina|Calabaza serpiente');
        $add('family-fabaceae','sun','safe',$H,'Phaseolus vulgaris|Frijol;Phaseolus lunatus|Frijol lima;Pisum sativum|Chícharo;Vicia faba|Haba;Cicer arietinum|Garbanzo;Lens culinaris|Lenteja;Arachis hypogaea|Cacahuate;Glycine max|Soya;Vigna unguiculata|Frijol caupí;Lablab purpureus|Frijol jacinto;Mimosa pudica|Dormilona;Delonix regia|Tabachín;Jacaranda mimosifolia|Jacaranda;Leucaena leucocephala|Guaje;Prosopis laevigata|Mezquite');
        $add('family-rutaceae','sun','caution',$H,'Citrus limon|Limonero;Citrus sinensis|Naranjo;Citrus reticulata|Mandarino;Citrus paradisi|Toronjo;Citrus aurantiifolia|Lima mexicana;Citrus maxima|Pomelo;Murraya paniculata|Limonaria;Ruta graveolens|Ruda;Casimiroa edulis|Zapote blanco;Poncirus trifoliata|Naranjo trifoliado;Zanthoxylum fagara|Palo amarillo;Aegle marmelos|Bael;Citrus medica|Cidro;Citrus japonica|Kumquat;Clausena lansium|Wampi');
        $add('family-myrtaceae','sun','safe',$H,'Psidium guajava|Guayabo;Eugenia uniflora|Pitanga;Syzygium aromaticum|Clavo de olor;Syzygium cumini|Jambolán;Myrtus communis|Mirto;Acca sellowiana|Feijoa;Callistemon citrinus|Limpiatubos;Eucalyptus globulus|Eucalipto;Melaleuca alternifolia|Árbol de té;Pimenta dioica|Pimienta gorda;Plinia cauliflora|Jabuticaba;Eugenia brasiliensis|Grumichama;Syzygium jambos|Pomarroso;Leptospermum scoparium|Manuka;Lagerstroemia indica|Árbol de Júpiter');
        $add('family-piperaceae','indoor','safe',$I,'Peperomia obtusifolia|Peperomia;Peperomia caperata|Peperomia ondulada;Peperomia argyreia|Peperomia sandía;Peperomia clusiifolia|Peperomia borde rojo;Peperomia graveolens|Peperomia rubí;Peperomia prostrata|Cadena de tortugas;Peperomia rotundifolia|Peperomia rastrera;Peperomia ferreyrae|Happy bean;Piper nigrum|Pimienta negra;Piper auritum|Hoja santa;Piper betle|Betel;Piper aduncum|Cordoncillo;Piper umbellatum|Acuyo;Peperomia polybotrya|Peperomia gota;Peperomia nitida|Peperomia rastrera');
        $add('family-polypodiaceae','humid','safe',$I,'Platycerium bifurcatum|Cuerno de alce;Polypodium aureum|Helecho azul;Polypodium vulgare|Polipodio;Phlebodium aureum|Helecho azul;Microsorum punctatum|Helecho cocodrilo;Microsorum diversifolium|Helecho canguro;Pyrrosia lingua|Pyrrosia;Campyloneurum phyllitidis|Lengua de venado;Pleopeltis polypodioides|Helecho resurrección;Drynaria rigidula|Helecho canasta;Lepisorus thunbergianus|Lepisoro;Nephrolepis exaltata|Helecho Boston;Adiantum raddianum|Culantrillo;Asplenium nidus|Nido de ave;Davallia fejeensis|Pata de conejo');
        return $rows;
    }
}
