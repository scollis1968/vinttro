<?php
namespace Custom\DataStaging;

class MapperFactory {
    public static function getMapper(string $source, string $dataType): StagingMapperInterface {
        // Clean strings to match file/class naming conventions (CamelCase)
        $cleanSource = str_replace([' ', '_', '-'], '', ucwords($source, ' _-'));
        $cleanType = str_replace([' ', '_', '-'], '', ucwords($dataType, ' _-'));
        
        $className = "Custom\\DataStaging\\Mappers\\" . $cleanSource . $cleanType . "Mapper";
        $filePath = __DIR__ . "/Mappers/" . $cleanSource . $cleanType . "Mapper.php";

        if (!file_exists($filePath)) {
            throw new \Exception("No mapper found for Source: '{$source}' and Type: '{$dataType}'. Expected file at: {$filePath}");
        }

        require_once $filePath;

        if (!class_exists($className)) {
            throw new \Exception("Mapper class '{$className}' not found inside {$filePath}");
        }

        return new $className();
    }
}