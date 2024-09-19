# SUMMARY

THE Washuas repo is used for all Drupal installs that require the use of Single Sign-On. 
This purpose of this module is to make it easy to keep the simpolesamlphp_auth
module version the same across the installations. It also contains the script 
for symlinking the cert, config, and metadata directories from 
/vendor/simplesamlphp/simplesamlphp to exports/nfsdrupal/all_drupal/config/simplesamlphp/
so that all the drupal installations use the same simplesamlphp application config.

# INSTALLATION

Make sure the following is added to the root composer.json file:

In the "repositories" section, please add the following:

    "washuas/washuas": {
      "url": "https://github.com/artsci-webteam/washuas.git",
      "type": "git",
      "reference": "v#"
    },

Next, add the washuas/washuas module to the installation:

`composer require washuas/washuas`

Finally, add the following to the "scripts" section so that the washuas module's
composer scripts can run.

> [!NOTE]
Composer does not automatically run scripts from dependencies.
> See https://github.com/composer/composer/issues/1193 for more detail.

      "post-install-cmd": [
          "@composer drupal:scaffold",
          "composer run-script post-install-cmd -d web/modules/custom/washuas"
      ],
      "post-update-cmd": [
          "@composer drupal:scaffold",
          "composer run-script post-install-cmd -d web/modules/custom/washuas"
      ],