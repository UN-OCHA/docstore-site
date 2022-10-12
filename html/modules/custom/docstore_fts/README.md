# Docstore FTS integration

## Clean

```bash
drush pm-uninstall docstore_fts
drush entity:delete node --bundle=fts
drush entity:delete taxonomy_term --bundle=fts_country
drush entity:delete taxonomy_term --bundle=fts_year
drush eval "\Drupal\node\Entity\NodeType::load('fts')->delete();"
drush eval "\Drupal\taxonomy\Entity\Vocabulary::load('fts_country')->delete();"
drush eval "\Drupal\taxonomy\Entity\Vocabulary::load('fts_year')->delete();"
drush en docstore_fts --verbose
```

## Import data

```bash
drush eval "docstore_fts_update_year(2022);" --verbose
drush eval "docstore_fts_update_year(2021);" --verbose
```

## Test

```bash
curl http://docstore-site.docksal.site/api/v1/fts/plan/iso3/afg | jq
curl http://docstore-site.docksal.site/api/v1/fts/plan/year/2020 | jq
```
