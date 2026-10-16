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
2. **Base de données** : créer la base et son utilisateur.
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
   ```

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
