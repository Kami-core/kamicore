UPDATE plugin_migrations AS pm
SET checksum = '3fde652cf429cc02b6a31aae0d86d4717cb087f9449a8596b16120e172a49261'
FROM plugins AS p
WHERE p.plugin_id = pm.plugin_id
  AND p.system_name = 'SystemManager'
  AND pm.migration_name = '002_system_assets.sql'
  AND pm.checksum = '1712a5874cd0397d4cbc8ca3e24913312dea6c0f935625fb666d8ef756817ce5';
