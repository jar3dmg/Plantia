<?php
declare(strict_types=1);

require __DIR__ . '/../back/bootstrap.php';

$db = database();
$families = $db->query("SELECT id, scientific_name, common_name FROM plant_taxa WHERE taxon_type='family' ORDER BY scientific_name")->fetchAll(PDO::FETCH_ASSOC);
$species = $db->query("SELECT p.parent_id,p.scientific_name,p.common_name,p.ecosystem,p.toxicity_level, GROUP_CONCAT(c.title ORDER BY c.title SEPARATOR ' | ') courses FROM plant_taxa p LEFT JOIN course_plant_taxa cpt ON cpt.plant_taxon_id=p.id LEFT JOIN courses c ON c.id=cpt.course_id WHERE p.taxon_type='species' GROUP BY p.id ORDER BY p.scientific_name")->fetchAll(PDO::FETCH_ASSOC);
$byFamily = [];
foreach ($species as $plant) $byFamily[(int)$plant['parent_id']][] = $plant;

$lines = [];
$lines[] = ['title', 'Catálogo botánico Plantia'];
$lines[] = ['text', 'Reporte de revisión generado el ' . date('d/m/Y H:i')];
$lines[] = ['text', 'Incluye familias, especies, ecosistema, seguridad para mascotas y relaciones con cursos.'];
$lines[] = ['space', ''];
$lines[] = ['title', 'Resumen'];
$lines[] = ['text', 'Familias: ' . count($families) . ' | Especies: ' . count($species)];
$lines[] = ['text', 'Relaciones curso-planta: ' . (int)$db->query('SELECT COUNT(*) FROM course_plant_taxa')->fetchColumn()];
$lines[] = ['space', ''];
foreach ($families as $family) {
    $name = $family['common_name'] ? $family['scientific_name'] . ' — ' . $family['common_name'] : $family['scientific_name'];
    $lines[] = ['family', $name];
    foreach ($byFamily[(int)$family['id']] ?? [] as $plant) {
        $common = $plant['common_name'] ?: 'Sin nombre común';
        $meta = implode(' · ', array_filter([$plant['ecosystem'] ?: 'sin ecosistema', $plant['toxicity_level'] ?: 'sin clasificación de seguridad']));
        $courses = $plant['courses'] ?: 'Sin relación de curso';
        $lines[] = ['plant', $common . ' — ' . $plant['scientific_name'] . ' | ' . $meta];
        $lines[] = ['course', 'Cursos: ' . $courses];
    }
    $lines[] = ['space', ''];
}

function pdfText(string $text): string {
    $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}
function wrapLine(string $text, int $width = 108): array {
    return explode("\n", wordwrap($text, $width, "\n", true));
}

$pages = [];
$current = [];
$lineCount = 0;
foreach ($lines as [$type, $text]) {
    $wrapped = $type === 'space' ? [''] : wrapLine($text, $type === 'course' ? 112 : 104);
    $needed = count($wrapped) + ($type === 'space' ? 1 : 0);
    if ($lineCount + $needed > 47) { $pages[] = $current; $current = []; $lineCount = 0; }
    foreach ($wrapped as $part) { $current[] = [$type, $part]; $lineCount++; }
}
if ($current) $pages[] = $current;

$objects = [];
$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
$pageIds = [];
for ($i = 0; $i < count($pages); $i++) $pageIds[] = 4 + ($i * 2);
$objects[] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn($id) => "$id 0 R", $pageIds)) . '] /Count ' . count($pages) . ' >>';
$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
foreach ($pages as $index => $page) {
    $pageId = $pageIds[$index];
    $contentId = $pageId + 1;
    $objects[$pageId - 1] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
    $stream = "BT\n/F1 9 Tf\n50 755 Td\n";
    $y = 755;
    foreach ($page as [$type, $text]) {
        $size = $type === 'title' ? 18 : ($type === 'family' ? 12 : ($type === 'plant' ? 9 : 8));
        $leading = $type === 'title' ? 25 : ($type === 'family' ? 18 : ($type === 'space' ? 8 : 12));
        $y -= $leading;
        if ($type !== 'space') $stream .= "/F1 {$size} Tf\n1 0 0 1 50 {$y} Tm\n(" . pdfText($text) . ") Tj\n";
    }
    $stream .= "ET\nBT /F1 8 Tf 1 0 0 1 500 24 Tm (Página " . ($index + 1) . ") Tj ET\n";
    $objects[$contentId - 1] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream";
}
$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $id => $object) { $offsets[$id + 1] = strlen($pdf); $pdf .= ($id + 1) . " 0 obj\n{$object}\nendobj\n"; }
$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
$targetDir = __DIR__ . '/../reports';
if (!is_dir($targetDir)) mkdir($targetDir, 0775, true);
$target = $targetDir . '/catalogo-plantas-plantia.pdf';
file_put_contents($target, $pdf);
echo "PDF generado: {$target}\n";
