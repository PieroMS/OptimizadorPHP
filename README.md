# Optimización de Código

# Instalar y probar el proyecto
- Ejecutar en la consola:
```
git clone "url del repo (repositorio > boton 'code' > url del repo)"
composer install
php artisan migrate (Laravel pregunta si desea crear la bd, seleccionar yes)
```

- ejecutar el archivo 'bdPrueba (1).sql' ubicado en 'optimizadophp/resources/assets' dentro de la base de datos creada

- Ejecutar en la consola:
```
php artisan serve
```

- poner en la url del navegador:
```
http://localhost:8000/api/documentation
```

# Optimizacion:

- Se creó un service llamado DatosService para separar la logica de negocio.

- Se optimizó las tres consultas en una.

- Se usó eloquent de Laravel.

- Se creó un controlador que ejecuta ese service.