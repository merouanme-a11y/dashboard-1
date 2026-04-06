# Procedure de deploiement production cPanel

## Objectif

Recreer un hebergement de production pour le projet `dashboard-1` a partir du local, sur cPanel, avec le meme nom de domaine :

`https://merouan.meha0348.odns.fr`

Cette procedure est adaptee au fonctionnement actuel du projet.

## Architecture cible

- Depot Git cPanel : `/home/meha0348/repositories/dashboard-1`
- Dossier web du domaine : `/home/meha0348/public_html`
- Domaine : `merouan.meha0348.odns.fr`
- Deploiement : pilote par `.cpanel.yml`

Important :

- Le domaine ne doit pas pointer vers `/home/meha0348/repositories/dashboard-1`
- Le domaine doit pointer vers `/home/meha0348/public_html`

## 1. Preparation locale

Avant toute mise en production, verifier sur le PC :

1. Le projet fonctionne bien en local.
2. Tous les changements de code a deployer sont commites.
3. Tous les commits sont pushes sur `origin/main`.
4. Les fichiers suivants existent dans le depot :
   - `.cpanel.yml`
   - `.env.prod`
   - `.htaccess`
   - `public/.htaccess`
   - `menu_config.json`
5. Les assets necessaires sont bien suivis par Git, notamment :
   - `public/assets/bootstrap-icons/bootstrap-icons.css`
   - `public/assets/bootstrap-icons/fonts/bootstrap-icons.woff`
   - `public/assets/bootstrap-icons/fonts/bootstrap-icons.woff2`

Commandes utiles en local :

```bash
git status
git log --oneline -n 5
git push origin main
```

## 2. Export de la base locale

Le projet ne depend pas seulement de Git. Le menu, les modules, les permissions, les pages et une partie de l'affichage viennent de la base de donnees.

Il faut donc exporter la base locale.

Points a retenir :

- Git transporte le code
- MySQL transporte les donnees
- `menu_config.json` transporte une partie de la configuration de menu

Faire un export complet de la base locale en SQL.

## 3. Preparation de l'hebergement cPanel

Dans cPanel :

1. Creer le domaine ou rattacher le domaine `merouan.meha0348.odns.fr`
2. Verifier que le `Document Root` du domaine est :

```text
/home/meha0348/public_html
```

3. Activer SSL
4. Regler la version PHP en `8.2` minimum
5. Creer une base MySQL et un utilisateur MySQL
6. Noter :
   - nom de la base
   - utilisateur MySQL
   - mot de passe MySQL

## 4. Creation du depot Git dans cPanel

Dans `Git Version Control` :

1. Cloner le depot dans :

```text
/home/meha0348/repositories/dashboard-1
```

2. Choisir la branche `main`

Verification a faire dans le terminal cPanel :

```bash
cd /home/meha0348/repositories/dashboard-1
git status
git log --oneline -n 5
```

## 5. Configuration d'environnement en production

Dans le dossier du repo serveur, creer un fichier non versionne :

```text
/home/meha0348/repositories/dashboard-1/.env.prod.local
```

Contenu type :

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=ton_secret
DEFAULT_URI=https://merouan.meha0348.odns.fr
DATABASE_URL="mysql://USER:PASS@127.0.0.1:3306/DBNAME?serverVersion=8.0.32&charset=utf8mb4"
```

Recommandation :

- creer aussi `.env.local` avec les memes valeurs si le projet ou certaines commandes console l'utilisent

Ne jamais commiter ces secrets dans Git.

## 6. Dependances PHP

Le projet Symfony a besoin du dossier `vendor/`.

Cas 1 : Composer existe sur le serveur

- laisser `.cpanel.yml` lancer l'installation

Cas 2 : Composer n'existe pas sur le serveur

- preparer `vendor/` depuis le local
- uploader l'archive ou le dossier dans :

```text
/home/meha0348/repositories/dashboard-1/vendor
```

Verification :

```bash
ls /home/meha0348/repositories/dashboard-1/vendor/autoload.php
```

## 7. Import de la base de donnees

Dans phpMyAdmin :

1. Selectionner la base de production
2. Faire une sauvegarde si besoin
3. Importer le fichier SQL exporte depuis le local

Important :

- si tu veux la meme prod que le local, il faut la meme base
- si le menu est different, verifier aussi l'utilisateur connecte

## 8. Premier deploiement cPanel

Dans `Git Version Control` :

1. Ouvrir le repo `dashboard-1`
2. Cliquer `Update from Remote`
3. Puis cliquer `Deploy HEAD Commit`

Le fichier `.cpanel.yml` actuel copie le projet vers :

```text
/home/meha0348/public_html
```

Il gere aussi :

- la copie des fichiers
- une tentative d'installation Composer si disponible
- la remise des permissions
- le vidage du cache Symfony

## 9. Si le bouton Deploy est grise

cPanel desactive le deploiement si le depot n'est pas propre.

Verification :

```bash
cd /home/meha0348/repositories/dashboard-1
git status
```

Si le depot contient des modifications locales :

```bash
git stash push -u -m "clean before deploy"
```

Puis :

```bash
git status
```

Il faut retrouver un depot propre avant de relancer `Deploy HEAD Commit`.

## 10. Verification apres deploiement

Verifier :

1. La page d'accueil
2. La page de login
3. Les icones
4. Les menus
5. Les modules
6. Les pages dynamiques

URLs a tester :

```text
https://merouan.meha0348.odns.fr/
https://merouan.meha0348.odns.fr/login
https://merouan.meha0348.odns.fr/assets/bootstrap-icons/bootstrap-icons.css
```

Si les icones ne s'affichent pas :

- verifier que les fichiers `public/assets/bootstrap-icons/` existent bien dans la prod
- verifier qu'ils ne sont pas ignores par Git

## 11. Si le site affiche une erreur 403

Cause frequente :

- mauvais droits sur `.htaccess`
- mauvais `Document Root`
- domaine pointe sur le repo au lieu de `public_html`

Commandes utiles :

```bash
chmod 711 /home/meha0348
chmod 755 /home/meha0348/repositories
find /home/meha0348/repositories/dashboard-1 -type d -exec chmod 755 {} \;
find /home/meha0348/repositories/dashboard-1 -type f -exec chmod 644 {} \;
find /home/meha0348/public_html -type d -exec chmod 755 {} \;
find /home/meha0348/public_html -type f -exec chmod 644 {} \;
```

Verifications :

- `Document Root` du domaine
- presence de `.htaccess`
- droits sur les fichiers

## 12. Si le site affiche une erreur 500

Causes frequentes :

- `vendor/` absent
- mauvaise variable `DATABASE_URL`
- cache Symfony obsolete
- permissions incorrectes sur certains fichiers vendor

Commandes utiles :

```bash
cd /home/meha0348/repositories/dashboard-1
php bin/console cache:clear --env=prod --no-debug
tail -n 80 error_log
tail -n 80 var/log/prod.log
```

Verifier aussi :

```bash
ls /home/meha0348/repositories/dashboard-1/vendor/autoload.php
```

## 13. Points specifiques a ce projet

Pour ce projet, il faut faire attention a :

- la base de donnees
- `menu_config.json`
- les permissions utilisateur
- les modules actifs
- le cache
- les assets d'icones

Le menu peut etre different entre local et prod meme avec le meme code si :

- la base n'est pas identique
- l'utilisateur connecte n'est pas le meme
- les permissions ne sont pas les memes
- les modules ne sont pas tous actifs

## 14. Routine normale ensuite

Pour les mises a jour futures :

1. Modifier et tester en local
2. Commit
3. Push vers GitHub
4. Dans cPanel :
   - `Update from Remote`
   - `Deploy HEAD Commit`
5. Reimporter la base si les donnees locales doivent remplacer la prod

## 15. Checklist finale

Avant mise en ligne :

- code pousse sur `main`
- `.cpanel.yml` present
- `.env.prod.local` cree sur le serveur
- base de production creee
- dump SQL importe
- `vendor/` disponible
- domaine pointe vers `public_html`
- SSL actif
- cache vide

Apres mise en ligne :

- `/` fonctionne
- `/login` fonctionne
- les icones sont visibles
- le bon utilisateur peut se connecter
- les menus correspondent au local
- les modules attendus apparaissent

## Emplacements importants

Repo Git :

```text
/home/meha0348/repositories/dashboard-1
```

Dossier web :

```text
/home/meha0348/public_html
```

Fichier d'environnement prod a creer :

```text
/home/meha0348/repositories/dashboard-1/.env.prod.local
```

## Fichiers importants du projet

- `.cpanel.yml`
- `.env.prod`
- `.htaccess`
- `public/.htaccess`
- `menu_config.json`
- `composer.json`

## Conclusion

La cle pour ne rien rater est simple :

- le code vient de Git
- les donnees viennent de MySQL
- les secrets viennent des fichiers `.env*.local`
- le domaine doit pointer vers `public_html`
- le deploiement doit passer par cPanel et `.cpanel.yml`

Si un seul de ces elements manque, la prod peut etre differente du local.
