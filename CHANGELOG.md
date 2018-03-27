# Changelog

The project follows Semantic Versioning (http://semver.org/)

## 1.2.0 - 2018-03-27
### Added
* ReferenceHelper::getReferenceData and ReferenceHelper::getObject to directly
  get entity identity information to store elsewhere (e.g. in queue jobs) and
  fetch entities by reference without a storing entity.

## 1.1.0 - 2018-03-20
### Added
* HasReferenceInterface::getFilterValues, ReferenceHelper::getClassFilterData
  and ReferenceHelper::getEntityFilterData to support querying
  the database for entities that reference objects of a specific class or a
  concrete objet

### Changed
* README

## 1.0.0 - 2018-03-10
Initial release