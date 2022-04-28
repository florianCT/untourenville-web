# Développer pour ChefToque sur Wordpress/Bedrock

Version Windows


## 1) installation

Installer :
- php
- vscode
- git
- xdebug (facultatif)
- plugin xdebug vscode (facultatif)
- déployer
- mysqldump
-mysql + workbench


## 2) Nouveau projet

•	Créer base
composer create-project roots/bedrock

•	à la racine du projet , installer composer

```
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

php -r "if (hash_file('sha384', 'composer-setup.php') === '795f976fe0ebd8b75f26a6dd68f78fd3453ce79f32ecb33e7fd087d39bfeb978342fb73ac986cd4f54edd0dc902601dc') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

php composer-setup.php

php -r "unlink('composer-setup.php');"
```

•	installer wp-cli

``curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar``

•	ajouter gitignore d’un autre projet

``cp otherproject/.gitignore .``

Puis initialiser repo git via vscode

•	ajouter fichier de conf deployer depuis autre projet

``cp otherproject/deploy.php .
cp otherproject/custom_deploy .``

•	créer répertoire localDbBackup

``mkdir localDbBackup``

•	remplir fichier .env avec credentials BDD de dev (à créer si besoin)

•	modifier deploy.php avec données des environnements distants (repository, host_test, host_prod)

•	déployer en test et configurer env de test (créer db si besoin)
``dep deploy
dep pushdb``


## 3) Premier lancement

•	Ajouter clé SSH serveur de test + prod

•	``git clone``

•	``php composer.phar update``

•	copier fichier .env (credentials)

•	``php -S 127.0.0.1:8000 -t web``

•	générer key ssh et copier en test et en prod


## 4) commandes 

|commande | objectif |
|----------|------------|
|``php -S 127.0.0.1:8000 -t web`` | Lancer serveur de dev |
|``dep deploy	| Déployer en test`` |
|``dep deploy -vvv``	| Déployer en test + verbose |
|``dep deploy production``	| Déployer en prod |
|``dep pulldb``	| récupérer BDD de test en local en gardant les utilisateurs locaux |
|``dep pulldb --db full``	| récupérer BDD de test en local avec les utilisateurs de test |
|``dep pulldb production``	| récupérer BDD de production |
|``dep pushdb``	| mettre en test la BDD |
|``dep pushdb --db full``	| mettre en test la BDD avec les utilisateurs locaux |
|``dep pushdb production`` |	mettre en prod la BDD |
|``dep plugins``	| Activer tous les plugins en local |
|``dep pushfiles``	| Upload Media Files |

## 5) installer plugins

``php composer.phar require wpackagist-plugin/woocommerce``

ou modifier composer.json
ne pas hésiter à utiliser https://wpackagist.org/ pour connaître nom plugin plus num de version
puis :

``php composer.phar update
dep plugins``
