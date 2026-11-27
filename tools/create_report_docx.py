from docx import Document
from docx.shared import Pt

d = Document()
d.add_heading('Plantia: sistema de aprendizaje adaptativo', 0)
def section(title, text):
    d.add_heading(title, level=1)
    d.add_paragraph(text)
section('Introducción', 'Este documento presenta Plantia, una plataforma web de aprendizaje de botánica. Describe sus funciones, usuarios, arquitectura y motor adaptativo, así como posibles mejoras futuras.')
section('Presentación del sistema', 'Plantia permite registrarse, crear un perfil, consultar cursos de botánica, avanzar en lecciones, realizar actividades y seguir una ruta de aprendizaje. Está dirigida a personas interesadas en cuidar plantas, desde principiantes hasta usuarios con experiencia. Considera intereses, objetivos, región, plantas, mascotas y nivel de conocimiento para personalizar las recomendaciones.')
section('Especificaciones técnicas', 'Plantia utiliza una arquitectura cliente-servidor. El navegador ejecuta HTML, CSS y JavaScript; una API en PHP procesa las solicitudes y accede a MySQL o MariaDB. Un motor escrito en Python calcula las recomendaciones. La información se intercambia mediante JSON.')
d.add_paragraph('Navegador → API PHP → Servicios de la aplicación → MySQL/MariaDB\n                                     ↕\n                          Motor adaptativo Python')
section('Descripción de usuarios', 'Los visitantes pueden conocer la plataforma y registrarse. Los usuarios registrados acceden a los cursos, su perfil y su progreso. El sistema contempla niveles principiante, intermedio y avanzado, además de características como intereses, plantas y presencia de mascotas.')
d.add_paragraph('Los siguientes perfiles son ejemplos ficticios, no datos de usuarios reales:')
t = d.add_table(rows=1, cols=5); t.style = 'Table Grid'
for c, x in zip(t.rows[0].cells, ['Usuario', 'Nivel', 'Interés', 'Objetivo', 'Mascotas']): c.text = x
rows = [('U1','Principiante','Interior','Cuidado','Sí'),('U2','Intermedio','Suculentas','Propagación','No'),('U3','Avanzado','Huerto','Cultivar alimentos','No'),('U4','Principiante','Botánica','Aprender bases','Sí'),('U5','Intermedio','Orquídeas','Cuidado','Sí'),('U6','Principiante','Jardín','Diseñar jardín','No'),('U7','Avanzado','Plagas','Resolver problemas','No'),('U8','Intermedio','Interior','Propagación','Sí'),('U9','Principiante','Macetas','Cuidado','No'),('U10','Intermedio','Compostaje','Cultivar alimentos','No')]
for row in rows:
    for c, x in zip(t.add_row().cells, row): c.text = x
section('Funciones adaptables', 'Desde su perfil, el usuario puede elegir su nivel de experiencia, tamaño de fuente pequeño, normal o grande, tema claro u oscuro, modo lectura y tipografía con ajustes para dislexia. El cuestionario inicial recopila entorno, intereses, objetivo, región, plantas y presencia de mascotas.')
section('Motor adaptativo: diagrama', 'Perfil, plantas, etiquetas de cursos y eventos\n                         ↓\n       Reglas priorizadas + señales de comportamiento\n                         ↓\n     Modelo de aprendizaje automático, si está entrenado\n                         ↓\n          Puntaje, orden y razones de recomendación\n                         ↓\n             Catálogo personalizado para el usuario\n                         ↓\n   Nuevas interacciones alimentan recomendaciones posteriores')
section('Motor adaptativo: detalles', 'El motor es híbrido: combina reglas declarativas, señales de comportamiento y una regresión logística opcional. PHP reúne el perfil, las etiquetas de los cursos, las reglas y hasta 200 eventos recientes; después envía esos datos al motor Python. Las reglas se procesan por prioridad y pueden sumar o restar puntos, mostrar avisos o excluir candidatos del resultado del motor.')
d.add_paragraph('Ejemplos de reglas sembradas: coincidencia de intereses y temas (+20 puntos), curso asociado a una planta registrada (+35), misma familia botánica (+25) y tres o más etiquetas coincidentes (+30). Las reglas dependen de que se cumplan sus condiciones y de los datos disponibles.')
d.add_paragraph('El comportamiento aporta puntos adicionales: el último curso abierto recibe 250 puntos por evento y el último inscrito 280; las coincidencias por etiquetas con cursos abiertos pueden sumar hasta 90. Los descartes generan penalizaciones y el servicio oculta cursos descartados que no estén inscritos.')
d.add_paragraph('Puntaje final = puntaje de reglas + puntaje de comportamiento + 30 × probabilidad del modelo')
d.add_paragraph('La regresión logística calcula la probabilidad con P = 1 / (1 + e⁻ᶻ), donde z es la suma ponderada de características como etiquetas, distancia entre niveles, duración y eventos previos. El entrenamiento usa ejemplos etiquetados de aceptación o descarte. Si no hay un modelo entrenado, la contribución de aprendizaje automático es cero y el sistema funciona con reglas y comportamiento.')
d.add_paragraph('El motor ordena los candidatos por puntaje descendente; en empate prioriza menor duración y luego título. Genera razones y reglas aplicadas. La aplicación guarda recomendaciones y puede recuperar el último orden almacenado si Python falla. La exclusión ocurre en la salida del motor; la integración web conserva en el catálogo los cursos que no aparecen en esa salida, por lo que no debe interpretarse como una garantía general de ocultamiento por seguridad.')
section('Trabajo a futuro', '1. Recomendación colaborativa: sugerir cursos a partir de patrones de usuarios con intereses similares. Necesitaría datos agregados de interacciones, avance, calificaciones y finalización.\n\n2. Recordatorios adaptativos: sugerir cuidados o momentos de estudio según las necesidades de cada persona. Necesitaría ubicación aproximada, calendario de cuidado, hábitos de acceso y preferencias de notificación.')
d.add_paragraph('Opiniones individuales sugeridas, para que cada integrante las adapte:')
d.add_paragraph('Integrante 1: Los sistemas adaptativos ayudan a reducir información irrelevante y facilitan el aprendizaje personalizado. Pueden utilizarse en educación.')
d.add_paragraph('Integrante 2: Su utilidad consiste en responder a necesidades distintas. Pueden apoyar actividades en salud y agricultura.')
d.add_paragraph('Integrante 3: Permiten ajustar la experiencia según el contexto de cada persona. También pueden utilizarse en accesibilidad y capacitación laboral.')
style = d.styles['Normal']; style.font.name = 'Calibri'; style.font.size = Pt(10)
d.save('reports/Plantia_documento.docx')
