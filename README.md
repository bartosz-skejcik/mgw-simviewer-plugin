# Simviewer GLPI plugin

Add your plugin description here.

## Known limitations

* **Home-page tile does not track later right changes.** The self-service
  home-page tile ("Podgląd SIM") is added to a profile's home page once, when
  the plugin is installed or updated, for every profile on the simplified
  (helpdesk) interface at that moment. If you later grant or revoke the
  `plugin_simviewer` READ right on a specific profile from that profile's
  admin tab, the tile is **not** added or removed automatically — the
  underlying page access is still correctly enforced (a revoked user gets a
  403 if they click a stale tile), but the tile itself can go stale or be
  missing. To resync tiles with the current per-profile rights, trigger a
  plugin repair/reinstall (Setup > Plugins) after changing rights on a
  profile.

## Contributing

* Open a ticket for each bug/feature so it can be discussed
* Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
* Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
* Work on a new branch on your own fork
* Open a PR that will be reviewed by a developer
