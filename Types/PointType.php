<?php

namespace Realestate\MssqlBundle\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;


class PointType extends Type
{
    const POINT = 'point';

    public function getName()
    {
        return self::POINT;
    }

    public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'POINT';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        list($longitude, $latitude) = sscanf($value, 'POINT(%f %f)');

        return array('latitude'=>$latitude, 'longitude'=>$longitude);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (is_array($value)) {
            $value = sprintf('POINT(%F %F)', $value['longitude'], $value['latitude']);
        }

        return $value;
    }

    public function canRequireSQLConversion()
    {
        return true;
    }

    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        var_dump($sqlExpr);die;
        return sprintf('%s.STAsText()', $sqlExpr);
    }

    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return sprintf('geography::STPointFromText(\'%s\', 4326)', $sqlExpr);
    }
}