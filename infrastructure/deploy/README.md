# Déploiement en production

Chaque push sur `main` dont la CI passe déclenche le workflow `Deploy` (relançable à la main depuis l'onglet Actions). Il compile le front, envoie l'archive par SSH puis exécute `deploy.sh` sur le serveur : nouvelle version dans `releases/`, `composer install --no-dev`, migrations, bascule du lien `current`. Les 3 dernières versions sont conservées.

```
~/sites/pousseur-de-galette/
├── current -> releases/<date>-<commit>
├── releases/
└── shared/
    └── .env.local   secrets de prod
```

## Installation initiale (une seule fois)

1. **Racine du site** (Manager Infomaniak) : `pdg.alexandrechauvin.fr` → `~/sites/pousseur-de-galette/current/public`, PHP 8.5, option « Forcer HTTPS ».
2. **Base de données** : créer une base MariaDB et son utilisateur. Dans `DATABASE_URL`, encoder le mot de passe pour l'URL puis doubler chaque `%` (le processeur `resolve:` de `doctrine.yaml` lit `%…%` comme un paramètre).
3. **Secrets de prod**, sur le serveur :
   ```bash
   mkdir -p ~/sites/pousseur-de-galette/shared
   nano ~/sites/pousseur-de-galette/shared/.env.local   # contenu : env.local.dist complété
   chmod 600 ~/sites/pousseur-de-galette/shared/.env.local
   ```
4. **Clé SSH de déploiement**, sur ton poste :
   ```bash
   ssh-keygen -t ed25519 -N '' -C 'github-deploy-pousseur-de-galette' -f pdg_deploy
   ```
   Ajouter `pdg_deploy.pub` à `~/.ssh/authorized_keys` sur le serveur, puis supprimer les deux fichiers du poste une fois le secret enregistré.
5. **Secrets GitHub** (Settings → Secrets and variables → Actions) :

   | Secret | Valeur |
   |---|---|
   | `SSH_HOST` | Hôte SSH du serveur |
   | `SSH_USER` | Utilisateur SSH |
   | `SSH_PRIVATE_KEY` | Contenu de `pdg_deploy` (clé privée) |
   | `SSH_KNOWN_HOSTS` | Sortie de `ssh-keyscan -t ed25519 <SSH_HOST>` |

6. **Premier déploiement** : pousser sur `main` ou lancer `Deploy` depuis l'onglet Actions.
7. **Worker Messenger** : `crontab -e` sur le serveur, puis ajouter :
   ```
   * * * * * cd ~/sites/pousseur-de-galette/current && /opt/php8.5/bin/php bin/console messenger:consume async --time-limit=55 --memory-limit=256M >> ~/sites/pousseur-de-galette/shared/worker.log 2>&1
   * * * * * cd ~/sites/pousseur-de-galette/current && flock -n /tmp/pdg-playback.lock /opt/php8.5/bin/php bin/console app:playback:run --duration=55 >> ~/sites/pousseur-de-galette/shared/playback.log 2>&1
   ```
   La seconde ligne est la boucle de lecture « juste à temps ». `flock` garantit qu'un seul passage tourne à la fois : deux boucles simultanées pourraient envoyer deux titres à Spotify.
8. **Hub temps réel** : l'hébergement ne peut pas faire tourner le binaire Mercure, le dossier `hub/` contient un petit hub compatible (Node, sans dépendance). Il tourne sur un site Node.js Infomaniak distinct (`ws.pdg.alexandrechauvin.fr`, dossier `~/sites/ws.pdg`), sur un autre serveur sans utilisateur SSH : il se déploie à la main depuis la console SSH du Manager.
   - Copier `server.mjs` : générer en local une commande à coller dans la console, puis vérifier que l'empreinte affichée est celle de `shasum -a 256 hub/server.mjs` (un collage tronqué la change) :
     ```bash
     printf "echo '%s' | base64 -d | gunzip > server.mjs && sha256sum server.mjs\n" "$(gzip -9nc hub/server.mjs | base64 | tr -d '\n')" | pbcopy
     ```
   - `package.json` : Infomaniak lance le site avec `npm start`, le fichier est donc obligatoire. Son script `start` charge le `.env`, contrairement à celui du dépôt (en local, Docker passe les variables) :
     ```json
     { "name": "hub", "private": true, "type": "module", "scripts": { "start": "node --env-file=.env server.mjs" } }
     ```
   - `.env` (`chmod 600`) :
     ```
     MERCURE_PUBLISHER_JWT_KEY=<même valeur que MERCURE_JWT_SECRET dans shared/.env.local>
     MERCURE_CORS_ORIGINS=https://pdg.alexandrechauvin.fr
     ```
   - Node ≥ 20.6 (pour `--env-file`). Le port est attribué par Infomaniak et lu dans `PORT`.
   - Dans `shared/.env.local`, `MERCURE_URL` et `MERCURE_PUBLIC_URL` pointent vers `https://ws.pdg.alexandrechauvin.fr/.well-known/mercure`.

   Après une modification de `hub/`, recopier `server.mjs` puis redémarrer le site depuis le Manager. `https://ws.pdg.alexandrechauvin.fr/` doit répondre `ok`. Le hub ne garde aucun historique ; à chaque (re)connexion, le front recharge l'état complet.

## Opérations courantes

- **Revenir à la version précédente** :
  ```bash
  cd ~/sites/pousseur-de-galette && ls -1t releases && ln -sfn releases/<version> current
  ```
  Une migration déjà jouée n'est pas annulée.
- **Messages en échec** :
  ```bash
  cd ~/sites/pousseur-de-galette/current && /opt/php8.5/bin/php bin/console messenger:failed:show
  ```
