<?php

namespace Helix\DB\Record;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Helix\DB;
use Helix\DB\EntityInterface;
use Helix\DB\Reflection;
use Helix\DB\Schema;
use stdClass;

/**
 * Converts an entity's values to/from storage types.
 */
class Serializer extends Reflection
{

    /**
     * Maps complex types to storage types.
     *
     * Foreign {@link EntityInterface} columns are automatically added as `"int"`
     *
     * @see Serializer::dehydrate()
     * @see Serializer::hydrate()
     * @see Schema::T_CONST_NAMES
     * @var string[]
     */
    protected $dehydrate = [
        'array' => 'STRING', // blob. eav is better than this for 1D arrays.
        'object' => 'STRING', // blob.
        stdClass::class => 'STRING', // blob
        DateTime::class => 'DateTime',
        DateTimeImmutable::class => 'DateTime',
    ];

    /**
     * Properties that are foreign keys to other entities.
     *
     * `[ property => foreign entity class ]`
     *
     * @var string[]
     */
    protected $foreign = [];

    /**
     * The specific classes used to hydrate classed properties, like `DateTime`.
     *
     * `[ property => class ]`
     *
     * @var string[]
     */
    protected $hydrate = [];

    /**
     * Scalar storage types, after any dehydration is done.
     *
     * `[property => type]`
     *
     * @var string[]
     */
    protected $storageTypes = [];

    protected DateTimeZone $utc;

    /**
     * @param DB $db
     * @param string|object $class
     */
    public function __construct(DB $db, $class)
    {
        parent::__construct($db, $class);
        $this->utc = new DateTimeZone('UTC');
        foreach ($this->columns as $col) {
            $type = $this->getType($col);
            if (is_a($type, EntityInterface::class, true)) {
                $this->foreign[$col] = $type;
                $this->dehydrate[$type] = 'int';
            }
            if (isset($this->dehydrate[$type])) {
                $this->hydrate[$col] = $type;
                $type = $this->dehydrate[$type];
            }
            $this->storageTypes[$col] = $type;
        }
        $this->storageTypes['id'] = 'int';
    }

    /**
     * Dehydrates a complex property's value for storage in a scalar column.
     *
     * @see Serializer::hydrate() inverse
     *
     * @param string $to The storage type.
     * @param string $from The strict type from the class definition.
     * @param array|object $hydrated
     * @return null|scalar
     */
    protected function dehydrate(string $to, string $from, $hydrated)
    {
        // we don't need $from here but it's given for posterity
        unset($from);

        // dehydrate entities to their id
        if ($hydrated instanceof EntityInterface) {
            return $hydrated->getId();
        }

        // dehydrate DateTime
        if ($to === 'DateTime') {
            assert($hydrated instanceof DateTime or $hydrated instanceof DateTimeImmutable);
            return (clone $hydrated)->setTimezone($this->utc)->format(Schema::DATETIME_FORMAT);
        }

        // dehydrate other complex types
        return serialize($hydrated);
    }

    /**
     * Returns an entity's property values, dehydrating if needed.
     *
     * @param EntityInterface $entity
     * @return array
     */
    public function export(EntityInterface $entity): array
    {
        $values = [];
        foreach ($this->columns as $col) {
            $value = $this->getValue($entity, $col);
            if (isset($value, $this->hydrate[$col])) {
                $from = $this->hydrate[$col];
                $to = $this->dehydrate[$from];
                $value = $this->dehydrate($to, $from, $value);
            }
            $values[$col] = $value;
        }
        return $values;
    }

    /**
     * @return string[]
     */
    final public function getForeign(): array
    {
        return $this->foreign;
    }

    /**
     * Returns the scalar storage types of all properties.
     *
     * This doesn't include whether the properties are nullable.
     * Use {@link Serializer::isNullable()} for that.
     *
     * @return string[]
     */
    final public function getStorageTypes(): array
    {
        return $this->storageTypes;
    }

    /**
     * Hydrates a complex value from scalar storage.
     *
     * @see Serializer::dehydrate() inverse
     *
     * @param string $to The strict type from the class definition.
     * @param string $from The storage type.
     * @param scalar $dehydrated
     * @return array|object
     */
    protected function hydrate(string $to, string $from, $dehydrated)
    {
        // hydrate entities from their id
        if (is_a($to, EntityInterface::class, true)) {
            return $this->db->getRecord($to)->load($dehydrated);
        }

        // hydrate DateTime
        if ($from === 'DateTime') {
            return new $to($dehydrated, $this->utc);
        }

        // hydrate other complex types
        $complex = unserialize($dehydrated);
        assert(is_array($complex) or is_object($complex));
        return $complex;
    }

    /**
     * Sets values from storage on an entity, hydrating if needed.
     *
     * @param EntityInterface $entity
     * @param array $values
     */
    public function import(EntityInterface $entity, array $values): void
    {
        foreach ($values as $property => $value) {
            if (isset($value)) {
                if (isset($this->hydrate[$property])) { // complex
                    $to = $this->hydrate[$property];
                    $from = $this->dehydrate[$to];
                    $value = $this->hydrate($to, $from, $value);
                } else { // scalar
                    // this function doesn't care about the type's letter case.
                    settype($value, $this->storageTypes[$property]);
                }
            }
            $this->setValue($entity, $property, $value);
        }
    }

}
