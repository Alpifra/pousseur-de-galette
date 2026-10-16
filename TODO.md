# Pousseur de Galette — Suivi du développement

Légende : `[ ]` à faire · `[~]` en cours · `[x]` terminé · `[!]` bloqué

---

## Phase 0 — Cadrage
- [x] Rédiger le cahier des charges (`presentation.md`)
- [x] Rédiger le brief de maquettage (`maquette.md`)
- [ ] Répondre aux questions ouvertes (§ 14 de `presentation.md`)
- [x] Choisir la stack technique (Symfony 8.1 / API Platform 5 + React 19 / Vite)
- [ ] Valider la couleur d'accent
- [ ] Vérifier les conditions actuelles de l'API Spotify (mode développement, quotas, charte, usage commercial)
- [ ] Faire réaliser les maquettes (Claude Design) et les valider

## Phase 1 — Initialisation
- [x] Initialiser le dépôt Git dédié au projet
- [x] Initialiser le projet avec la stack retenue (`api/` Symfony + API Platform, `front/` React + TypeScript strict)
- [~] Configurer lint, formatage, hooks pre-commit (PHPStan niv. 8, PHP-CS-Fixer, oxlint en place ; hooks pre-commit à faire)
- [x] Mettre en place la base de données et l'ORM (PostgreSQL 18 + Doctrine)
- [~] Variables d'environnement : `api/.env` (DB, Mercure, Spotify) en place ; clé de chiffrement des tokens à ajouter
- [ ] Créer l'app sur le Spotify Developer Dashboard et déclarer les redirect URIs
- [ ] Mettre en place les design tokens (couleurs, typo, espacements) issus des maquettes
- [x] Mettre en place l'infra Docker (Caddy, PHP-FPM, worker Messenger, Mercure, PostgreSQL, Node) + Makefile
- [x] Mettre en place la CI (lint + tests) et le déploiement continu sur le VPS Infomaniak (`infrastructure/deploy/README.md`)
- [ ] Renseigner les secrets GitHub (`SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`) et `shared/.env.local` sur le VPS
- [ ] Créer la base PostgreSQL de prod et pointer le vhost `pdg.alexandrechauvin.fr` sur `current/public`
- [ ] **Choisir la solution temps réel de prod** : pas de Docker sur le VPS, donc pas de hub Mercure conteneurisé. Pistes : binaire Mercure lancé en service, hub Mercure hébergé, ou service tiers (Pusher / Ably). Tant que ce point n'est pas tranché, le temps réel ne fonctionne qu'en local.
- [ ] Vérifier que le VPS autorise un processus long pour le worker Messenger ; sinon s'appuyer sur le cron `messenger:consume --time-limit=55` (voir `infrastructure/deploy/README.md`)

## Phase 2 — Intégration Spotify
- [ ] OAuth Authorization Code + PKCE (connexion du compte maître)
- [ ] Stockage chiffré des tokens + rafraîchissement automatique
- [ ] Vérification du compte Premium
- [ ] Client API Spotify (gestion du 429 / `Retry-After`, erreurs réseau)
- [ ] Recherche de titres (endpoint serveur + cache)
- [ ] Lecture de l'état du lecteur / titre en cours
- [ ] Liste et sélection des appareils
- [ ] Ajout d'un titre à la file Spotify
- [ ] Passer au titre suivant

## Phase 3 — Cœur métier
- [ ] Modèle de données : Host, Event, Guest, QueueItem, Vote
- [ ] Création / démarrage / pause / clôture d'un événement
- [ ] Génération du code court et du QR code
- [ ] Session convive anonyme (pseudo + cookie signé)
- [ ] Proposition d'un titre (doublons → upvote, quota par convive, délai avant rediffusion)
- [ ] Votes up / down (unicité, changement, annulation)
- [ ] Calcul du score et classement (épinglage, ancienneté)
- [ ] Seuil de rejet automatique
- [ ] Filtres : explicite, durée max
- [ ] Paramètres d'événement

## Phase 4 — Worker de lecture « juste à temps »
- [ ] Boucle de suivi par événement actif
- [ ] Verrouillage et envoi du titre n°1 à T−15 s
- [ ] Détection du changement de titre → mise à jour des statuts
- [ ] Gestion des titres « hors file » (ajoutés directement dans Spotify)
- [ ] Gestion pause / appareil déconnecté / token expiré
- [ ] Gestion de la file vide
- [ ] Reprise après redémarrage du serveur

## Phase 5 — Temps réel
- [ ] Canal par événement
- [ ] Diffusion : file, votes, titre en cours, statut de l'événement
- [ ] Reconnexion automatique + resynchronisation de l'état
- [ ] Indicateur de connexion côté client

## Phase 6 — Interface convive (mobile-first)
- [ ] Écran « Rejoindre » (code / QR)
- [ ] Saisie du pseudo
- [ ] Écran principal : titre en cours + file
- [ ] Boutons de vote avec retour visuel immédiat (optimistic UI)
- [ ] Recherche avec debounce + résultats
- [ ] Confirmation de proposition
- [ ] « Mes galettes » (mes propositions)
- [ ] États : vide, chargement, erreur, hors ligne, événement terminé/en pause, banni

## Phase 7 — Interface organisateur
- [ ] Inscription / connexion
- [ ] Connexion du compte Spotify
- [ ] Liste et création d'événements
- [ ] Tableau de bord live : titre en cours, file, alertes
- [ ] Modération : retirer, épingler, passer
- [ ] Paramètres de l'événement
- [ ] Affichage / téléchargement du QR code

## Phase 8 — Qualité et mise en production
- [ ] Tests unitaires (classement, règles de proposition, votes)
- [ ] Tests d'intégration (client Spotify mocké, worker)
- [ ] Tests E2E des parcours convive et hôte
- [ ] Test de charge (300 convives simultanés)
- [ ] Test en conditions réelles (soirée test)
- [ ] Limitation de débit et anti-abus
- [ ] Mentions légales, politique de confidentialité, purge des données
- [ ] Hébergement, domaine, HTTPS
- [ ] Monitoring et logs d'erreurs
- [ ] Demande d'extension de quota Spotify (si ouverture publique)

## V2 — Évolutions
- [ ] Écran de diffusion (mode TV)
- [ ] Bannissement d'un convive
- [ ] Playlist de secours
- [ ] Export de la soirée en playlist Spotify
- [ ] Récapitulatif de fin de soirée
- [ ] Notifications « ta galette passe ! »
- [ ] Liste de titres / artistes interdits
- [ ] « Moments » programmés (première danse, gâteau…)
- [ ] PWA installable
- [ ] Internationalisation FR / EN

---

## Journal des décisions

| Date | Décision |
|---|---|
| 16/10/2026 | La file de référence est gérée par la plateforme ; Spotify ne reçoit qu'un titre à la fois (« juste à temps »), car son API ne permet pas de retirer ou réordonner sa file. |
| 16/10/2026 | Stack : Symfony 8.1 + API Platform 5 (API), React 19 + Vite (front), PostgreSQL 18, Mercure pour le temps réel, Messenger + Scheduler pour le worker de lecture. Infra Docker calquée sur le projet iMariage. |
| 16/10/2026 | En local, l'app est servie sur `https://127.0.0.1:8443` (et non `localhost`) : Spotify n'accepte plus `localhost` dans les redirect URIs, seulement l'IP de loopback explicite. |
| 16/10/2026 | Déploiement calqué sur iMariage : la CI compile le front et l'injecte dans `api/public/`, envoie une archive par SSH, puis `deploy.sh` crée une version dans `releases/` et bascule le lien `current`. Apache sert `current/public`, le `.htaccess` route `/api` vers Symfony et le reste vers le front React. |
| 16/10/2026 | `MERCURE_URL` ne doit jamais être vide en prod : le bundle basculerait sur le hub intégré de FrankenPHP, absent sous Apache, et `cache:clear` échouerait. |
