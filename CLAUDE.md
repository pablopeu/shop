# Configuración del Proyecto Shop (Ecommerce)

## Editor Preferido
- **Editor**: nano
- Usar nano para todas las ediciones de archivos de texto en este proyecto y toda vez que CLaude quiera usar vi evitarlo y usar nano

## Información del Proyecto
- **Tipo**: Sitio de ecommerce
- **Ruta**: /home/pablo/shop
- **Branch principal**: main

## Tecnologías Utilizadas
html php js

## Estructura del Proyecto
[Describir la estructura de carpetas principales]

## Convenciones de Código
en el proyecto no se usan msgbox ni alertbox, 
si en cualquier interaccion al abrir un archivo ves que hay un msgbox o alertbox, 
avisarme y lo cambiamos por el modal reutilizable de /admin/includes
el sistema debe referir todos sus path en relacion al de instalacion, nunca hardcodear paths
si en algun momento se detecta un path que este hardcodeado avisarme inmediatamente
siempre que se termine un prompt se debera hacer el commit al branch local y al branch correspondiente en github
el sistema utiliza themes que se guardan en la carpeta themes 
siempre hay un theme activo
si se detectan css en cualquier codigo que se edite que deba estar en un theme avisarme inmediatamente para agregarlo al theme
siempre que se hagan cambios en el theme activo deberan propagarse a los demas themes si es algo que no estaba en los themes previamente.

