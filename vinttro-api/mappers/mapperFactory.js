const path = require('path');

class MapperFactory {
    static getMapper(source = 'acturis', dataType = 'client') {
        const cleanSource = source.toLowerCase();
        const cleanType = dataType.charAt(0).toUpperCase() + dataType.slice(1).toLowerCase();
        const mapperFileName = `${cleanSource}${cleanType}Mapper.js`;
        const mapperPath = path.join(__dirname, mapperFileName);

        try {
            const MapperClass = require(mapperPath);
            return new MapperClass();
        } catch (err) {
            throw new Error(`Mapper not found: '${mapperFileName}' at ${mapperPath}`);
        }
    }
}

module.exports = MapperFactory;