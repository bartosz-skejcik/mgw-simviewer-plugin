# Simviewer GLPI plugin

Add your plugin description here.

## Known limitations

* **Home-page tile is entity-wide, not right-scoped.** The self-service
  home-page tile ("Podgląd SIM") is linked to the root entity (so it appears
  *alongside* the entity's default Service Catalog tiles — GLPI replaces the
  whole default tile set when a profile carries its own tiles, which is why
  the tile is not profile-linked). Every helpdesk-interface user of the
  entity sees the tile regardless of the `plugin_simviewer` READ right; page
  access is still enforced server-side (a user without the right gets a
  rights error when clicking). All helpdesk profiles are granted READ at
  install, so in practice this only matters if an admin later revokes the
  right on a profile.

## Contributing

* Open a ticket for each bug/feature so it can be discussed
* Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
* Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
* Work on a new branch on your own fork
* Open a PR that will be reviewed by a developer
