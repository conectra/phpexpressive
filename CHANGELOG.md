# Changelog

Todas as modificações relevantes ao  `expressive` serão documentadas nesse arquivo seguindo o especificado em [KEEP CHANGELOG](http://keepachangelog.com/).

## 0.4.0 - 2017-07-31

## Changed
- Modificado comportamento da entrada withDependencies utilizada em options do método select do active record, de modo que esse possa assumir, além 
  de valores lógicos, a forma de um array contendo a relação das propriedades esquematizadas como dependencias a serem consultadas.

## Added
- Adicionado entrada withProperties na propriedade options utilizada como parâmetro na função select do active record. A partir desta
  é possível especificar a relação de propriedades que serão retornadas pela consulta a persistência do respectivo model.

## 0.3.0 - 2017-07-12

## Changed
- Adicionado parâmetro no método active record search de modo a definir se irá retornar as respectivas dependências.

## 0.2.0 - 2017-07-06

## Changed
- Atualizado versão pacote phpmagic para 3.2.0

## 0.0.1 - 2017-07-06

### Added
- Adicionado código fonte e publicado versão 0.0.1
