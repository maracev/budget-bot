# Guías de Desarrollo

## Principios Generales

* Actúa como un desarrollador Senior de Laravel.
* Prioriza la simplicidad, legibilidad, mantenibilidad y testabilidad.
* Favorece cambios incrementales por sobre grandes refactorizaciones.
* Evita la sobreingeniería y las abstracciones especulativas.
* Minimiza la cantidad de archivos y la complejidad siempre que sea posible.
* Antes de introducir un nuevo patrón o capa de abstracción, justifica por qué es necesaria.

## Convenciones Laravel

* Prioriza las convenciones de Laravel por sobre arquitecturas personalizadas.
* Respeta las convenciones de nombres y estructura de Laravel.
* Utiliza inyección de dependencias cuando corresponda.
* Prefiere las herramientas que ofrece el framework antes de crear soluciones propias.
* Utiliza Form Requests para validaciones en endpoints HTTP cuando corresponda.

## Arquitectura

* Reutiliza servicios y patrones existentes antes de crear nuevos.
* No introduzcas repositorios a menos que el proyecto ya los utilice o exista una necesidad clara.
* No introduzcas interfaces para una única implementación.
* Mantén controladores y handlers lo más simples posible.
* Ubica las reglas de negocio fuera de los controladores cuando la complejidad lo justifique.
* Respeta la arquitectura y el estilo de código ya presentes en el proyecto.

## Calidad de Código

* Aplica principios de Clean Code.
* Utiliza nombres descriptivos y que reflejen la intención.
* Mantén métodos y clases enfocados en una única responsabilidad.
* Prefiere código explícito y fácil de entender por sobre soluciones ingeniosas pero difíciles de mantener.
* Elimina código muerto, duplicado o sin uso.

## Testing

* Agrega o actualiza tests para cada cambio de comportamiento.
* No consideres una tarea finalizada hasta que los tests relevantes pasen correctamente.
* Las correcciones de errores deben incluir tests de regresión cuando corresponda.
* Reutiliza los patrones y convenciones de testing ya existentes en el proyecto.

## Pull Requests

Antes de considerar una tarea finalizada:

1. Ejecuta los tests relevantes.
2. Verifica formato y estilo del código.
3. Revisa el impacto del cambio realizado.
4. Resume las decisiones técnicas o arquitectónicas tomadas.
5. Identifica riesgos, limitaciones y posibles mejoras futuras.

## Qué evitar

* No introducir capas de abstracción innecesarias.
* No crear patrones complejos para resolver problemas simples.
* No reescribir código existente sin una razón clara.
* No realizar cambios masivos cuando un cambio pequeño resuelve el problema.
* No incorporar dependencias externas sin justificar su valor.
