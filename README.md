Helix::DB
=========

Database storage and access using annotations.

[![](https://img.shields.io/badge/PHP-~7.4-666999)](https://www.php.net)
[![](https://img.shields.io/badge/packagist-a50)](https://packagist.org/packages/hfw/db)
[![](https://img.shields.io/badge/license-MIT-black)](LICENSE.txt)
[![](https://scrutinizer-ci.com/g/hfw/db/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/hfw/db)
[![](https://scrutinizer-ci.com/g/hfw/db/badges/build.png?b=master)](https://scrutinizer-ci.com/g/hfw/db)

Documentation: https://hfw.github.io/db

Class Annotations
-----------------

```
/**
 * @record my_table
 */
class MyClass implements Helix\DB\EntityInterface, ArrayAccess {

    use Helix\DB\AttributesTrait;

    /**
     * "id" is a required column.
     * @column
     * @var int
     */
    protected $id = 0;
    
    /**
     * @column
     * @var string
     */
    protected $myColumn;
    
    /**
     * @eav foo_eav
     * @var array
     */
    protected $attributes;
    
    /**
     * @return int
     */
    final public function getId() {
        return $this->id;
    }

}
    
```

* Columns must be named the same as their respective properties.
* EAV tables must have 3 columns: `entity`, `attribute`, and `value`.
    * `entity` must be a foreign key.
    * `entity` and `attribute` must form the primary key.

Interface Annotations
---------------------

Interfaces can be annotated to act as junctions.

```
/**
 * @junction foo_bar
 * @foreign foo_id Foo
 * @foreign bar_id Bar
 */
interface FooBar { }
```

* The interfaces don't have to be implemented.
* The referenced classes may be identical.

Supported Drivers
-----------------

- MySQL
- SQLite

Class Diagram
-------------

[![](https://hfw.github.io/db/classes.png)](https://hfw.github.io/db/inherits.html)
