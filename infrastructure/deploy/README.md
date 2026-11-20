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
8. **Hub temps réel** : l'hébergement ne peut pas faire tourner le binaire Mercure, le dossier `hub/` contient un petit hub compatible (Node, sans dépendance). Créer un site Node.js dans le Manager, sur son propre sous-domaine (`HUB_HOST`) :
   ```bash
   ssh <serveur> 'mkdir -p ~/sites/pousseur-de-galette-hub'
   scp hub/server.mjs hub/package.json <serveur>:sites/pousseur-de-galette-hub/
   ```
   - Dossier d'exécution : `~/sites/pousseur-de-galette-hub`, sans commande de build.
   - Commande de lancement : `node --env-file=.env server.mjs`, avec dans `.env` (`chmod 600`) :
     ```
     MERCURE_PUBLISHER_JWT_KEY=<même valeur que MERCURE_JWT_SECRET dans shared/.env.local>
     MERCURE_CORS_ORIGINS=https://pdg.alexandrechauvin.fr
     ```
   - Port : celui attribué par le Manager, lu dans `PORT`.
   - Dans `shared/.env.local`, `MERCURE_URL` et `MERCURE_PUBLIC_URL` pointent vers `https://HUB_HOST/.well-known/mercure`.

   Le hub n'est pas déployé par la CI : après une modification de `hub/`, refaire le `scp` puis redémarrer le site depuis le Manager. Il ne garde aucun historique ; à chaque (re)connexion, le front recharge l'état complet.

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
