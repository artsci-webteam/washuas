# SSO Assign Roles at Login

The SSO Assign Roles at Login module allows the web team to manage user roles
for A&S staff. The roles are automatically
assigned when these users authenticate via SSO.

To view the module repo, visit:
[SSO Git Repo](https://github.com/artsci-webteam/sso).

## Requirements

This module requires the following modules:

- [simpleSAMLphp Authentication](https://www.drupal.org/project/simplesamlphp_auth/)
- [WashU A&S](https://github.com/artsci-webteam/washuas)

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

> [!NOTE]  
> This module should only be installed in environments that use simplesaml
> for logging in.

## Configuration

### Adding new email addresses to automatically assign roles at login

1. Open the web/modules/custom/sso/modules/sso_assign_roles_at_login/config/install/sso_assign_roles_at_login.settings.yml file
2. List the email addresses that should be assigned a given role
   1. For example, the following configuration will assign the
      "administrator" role to the list of email addresses in both the artsci
      and research server environment:

> [!NOTE]
> The pattern for each config key is "role_configkey_env". For example, the
> `administrator` role in `research`  would be `administrator_sso_login_emails_research`
> To add a new email to the `administrator` role in `research`, the email would get 
> added under "administrator_sso_login_emails_research"
>
> If the role machine name ever changes, the role machine name in the config_key must
> also be updated.


   ```
   administrator_sso_login_emails_artsci:
     - marcia@wustl.edu
     - amybaker@wustl.edu
     - luongo@wustl.edu
     - daniellew@wustl.edu
     - tretter@wustl.edu
     - carmelas@wustl.edu
     - bulleri@wustl.edu
   administrator_sso_login_emails_research:
     - marcia@wustl.edu
     - amybaker@wustl.edu
     - luongo@wustl.edu
     - daniellew@wustl.edu
     - tretter@wustl.edu
     - carmelas@wustl.edu
     - bulleri@wustl.edu
     - g.porter@wustl.edu
   ```

3. Run a partial config import to import the changes: `drush cim --partial --source=modules/custom/sso/modules/sso_assign_roles_at_login/config/install/`

### Removing roles from users who were once added to the configuration

This module only assigns roles to email address when those users log into a
site via sso. Removing their email address from this module's configuration
will not remove the role from the users.

To remove  a role from the user, remove the email from 
`sso_assign_roles_at_login.settings`, and run the 
following drush command across the multisites:

`drush user:role:remove 'role' user`

## Testing

To verify that the email address added to this module's config is getting
assigned the correct role, in the correct environment, have the user sign
in to the website using SSO and confirm that the correct roles were added to
the user account.
