#MyQ

es un pequeño gestor de consultas SQL, este permite construir una consulta inmutable

```php

require __DIR__."/../vendor/autoload.php";

$pdo = new PDO("mysql:dbname=attack;host=localhost", "root", "");

$db = new MyQ\Connect([
    "db"    => $pdo,
    "prefix"=> "sample_" // opcional
]);

$data = $db->el_nombre_de_mi_tabla // prepara el cursor hacia esta tabla
          ->select()   // prepara la consulta select
          ->fetch();   // la ejecuta para obtener un resultado

```

## Métodos

por defecto MyQ enseña diversos métodos que aceleran el proceso de generación de consultas SQL

## MyQ::select( array $select = [] )

prepara una consulta select

### select ejemplo 1

permite obtener de **"mi_tabla"** el campo **"mi campo"**

```php
$mi_tabla->select([
   "mi campo"
])

```

### select ejemplo 2

permite obtener de **"mi_tabla"** el campo **"mi campo"** y asignarle el alias **"campo"**

```php
$mi_tabla->select([
   [
       "mi campo",
       "campo"
   ] // mi campo as campo
])

```


### select ejemplo 3

permite obtener de **"mi_tabla"** el campo **"mi campo"** aplicar las funciones **"MIN, MAX, COUNT , AVG, SUB"**

```php
$mi_tabla->select([
   [
       "mi campo",
       "min"
   ] // MIN(mi campo)
])

```

### select ejemplo 4

permite obtener de **"mi_tabla"** el campo **"mi campo"** aplicar las funciones **"MIN, MAX, COUNT , AVG, SUB"** y asignar un alias

```php
$mi_tabla->select([
   [
       "mi campo",
       "min",
       "minimo"
   ] // MIN(mi campo) as minimo
])

```

## MyQ::insert(array $columns)

prepara una consulta insert

```php
$mi_tabla->insert([
   "mi campo"=>10,
   "mi otro campo"=>20,
])
```


## MyQ::update(array $columns)

prepara una consulta update

```php
$mi_tabla->update([
   "mi campo"=>10,
   "mi otro campo"=>20,
])
```


## MyQ::delete(array $columns)

prepara una consulta delete

```php
$mi_tabla->update()
```

## MyQ::where(array $where)

concatena a la consulta la sentencia where

### Ejemplo where
esta consulta elimina de **"mi_tabla"**, la o las filas que cumpla con la expresión **"id = 10"**

```php
$mi_tabla
->delete()
->where([
   "id","=","10"
])

```

> de igual forma ud puede usar todas las expresiones de búsqueda propias de where **"=, <, >, <=, >=, <>"

### Ejemplo where IS NULL

esta consulta elimina de **"mi_tabla"**, la o las filas que cumpla con la expresión **"IS NULL"**

```php
$mi_tabla
->delete()
->where([
   "id","=",NULL
])

```

### Ejemplo where NOT !

esta consulta elimina de **"mi_tabla"**, la o las filas que cumpla con la expresión **"NOT id = 10"**

```php
$mi_tabla
->delete()
->where([
   "id","!=",10
])

```

### Ejemplo where BETWEEN {}

De igual forma ud puede aplicar el comodín **BETWEEN** sea usando la palabra **"between"** o su comodín **"[]"**.

esta consulta elimina de **"mi_tabla"**, la o las filas que cumpla con la expresión **"ID BETWEEN 1 AND 20"**

```php
$mi_tabla
->delete()
->where([
   "id","[]",[1,20]
])

```

### Ejemplo where IN {}

De igual forma ud puede aplicar el comodín **IN** sea usando la palabra **"in"** o su comodín **"{}"**.

esta consulta elimina de **"mi_tabla"**, la o las filas que cumpla con la expresión **"ID IN (1,20)"**

```php
$mi_tabla
->delete()
->where([
   "id","{}",[1,20]
])

```


### Ejemplo where OR ||

De igual forma ud puede aplicar el comodín **OR** sea usando la palabra **"or"** o su comodín **"||"**.

esta consulta elimina de **"mi_tabla"**, la o las filas que cumpla con la expresión **"id = 2 OR id = 3"**

```php
$mi_tabla
->delete()
->where([
   ["id","=",2],"||",["id","=",3]
])

```

### Ejemplo where like %

De igual forma ud puede aplicar el comodín **LIKE** sea usando la palabra **"like"** o su comodín **"%"**.

esta consulta elimina de **"mi_tabla"**, la o las filas que cumpla con la expresión **"name LIKE `%m%` "**

```php
$mi_tabla
->delete()
->where([
   "name","%","%m%"
])

```

### Ejemplo where completo

de igual forma ud puede usar todos los operadores para crear consultas avanzadas, incluso recurrir a la recursión de estos mismos.

```php
$mi_tabla
->delete()
->where([
   "id","=","20",
   "age", "[]",[18,30],
   "lang", "{}", ["es","en"]
])
```

## MyQ::join( array $joins , $type = "inner")


concatena a la consulta la sentencia join

```php
$mi_tabla
->select([
   "mi_tabla.*",
   "mi_otra_tabla.*"
])
->join([
   "mi_otra_tabla.ID"=>"mi_tabla.ID"
])
```

## MyQ::raw( string $raw )

permite concatenar a la consulta ya existente un string sin validación de seguridad

## MyQ::setParams( array $params)

permite modificar o crear parámetros para la consulta

```php
$mi_tabla
->select()
->setParams([
   ":ID"=>10
])
->raw("WHERE ID=:ID")
```

## MyQ::fetch( fetch_style $fetch = \PDO::FETCH_ASSOC , fetch_argument ...$args )

Permite ejecutar  de forma conjunta **"execute"** y luego **"fetchAll"** con la constante **"fetch"** asignada.

```php
$mi_tabla
->select()
->fetch()
```

## MyQ::execute( array $params = [] )

permite ejecutar el método **"execute"** para obtener los resultados de la query, **"$params"** puede reemplazar los parámetros por defectos anteriormente existentes en la query.

este retorna un array que se compone de la siguiente forma `[$prepare,$status]`:

* **$prepare** : posee el retorno de **PDO::prepare**
* **$status** : posee el retorno de **PDO::execute**

