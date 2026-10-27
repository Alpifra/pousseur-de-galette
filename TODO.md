# Pousseur de Galette — Suivi du développement

Légende : `[ ]` à faire · `[~]` en cours · `[x]` terminé · `[!]` bloqué

---

## Phase 0 — Cadrage
- [x] Rédiger le cahier des charges (`presentation.md`)
- [x] Rédiger le brief de maquettage (`maquette.md`)
- [ ] Répondre aux questions ouvertes (§ 14 de `presentation.md`)
- [x] Choisir la stack technique (Symfony 8.1 / API Platform 5 + React 19 / Vite)
- [ ] Valider la couleur d'accent
- [x] Vérifier les conditions actuelles de l'API Spotify (mode développement, quotas, charte, usage commercial) — voir `presentation.md` § 7.5
- [ ] Faire réaliser les maquettes (Claude Design) et les valider

## Phase 1 — Initialisation
- [x] Initialiser le dépôt Git dédié au projet
- [x] Initialiser le projet avec la stack retenue (`api/` Symfony + API Platform, `front/` React + TypeScript strict)
- [x] Configurer lint, formatage, hooks pre-commit (PHPStan niv. 8, PHP-CS-Fixer, oxlint, hook `.githooks/pre-commit` activable avec `make hooks`)
- [x] Mettre en place la base de données et l'ORM (PostgreSQL 18 + Doctrine)
- [x] Variables d'environnement : `api/.env` (DB, Mercure, Spotify, `APP_CRYPTO_KEY`, `SPOTIFY_MARKET`) ; à reporter dans `shared/.env.local` en prod
- [~] Créer l'app sur le Spotify Developer Dashboard et déclarer les redirect URIs (app créée, identifiants en local ; redirect URI de prod à déclarer)
- [x] Mettre en place les design tokens (couleurs, typo, espacements) issus des maquettes (`front/src/styles/tokens.css`)
- [x] Mettre en place l'infra Docker (Caddy, PHP-FPM, worker Messenger, Mercure, PostgreSQL, Node) + Makefile
- [x] Mettre en place la CI (lint + tests) et le déploiement continu sur le VPS Infomaniak (`infrastructure/deploy/README.md`)
- [ ] Renseigner les secrets GitHub (`SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`) et `shared/.env.local` sur le VPS
- [ ] Créer la base PostgreSQL de prod et pointer le vhost `pdg.alexandrechauvin.fr` sur `current/public`
- [ ] **Choisir la solution temps réel de prod** : pas de Docker sur le VPS, donc pas de hub Mercure conteneurisé. Pistes : binaire Mercure lancé en service, hub Mercure hébergé, ou service tiers (Pusher / Ably). Tant que ce point n'est pas tranché, le temps réel ne fonctionne qu'en local.
- [ ] Vérifier que le VPS autorise un processus long pour le worker Messenger ; sinon s'appuyer sur le cron `messenger:consume --time-limit=55` (voir `infrastructure/deploy/README.md`)

## Phase 2 — Intégration Spotify
- [x] OAuth Authorization Code + PKCE (connexion du compte maître)
- [x] Stockage chiffré des tokens + rafraîchissement automatique
- [x] Vérification du compte Premium : `GET /me` ne renvoie plus `product`, le 403 `PREMIUM_REQUIRED` des endpoints lecteur devient l'erreur de lecture `premium_required`
- [x] Client API Spotify (gestion du 429 / `Retry-After`, erreurs réseau)
- [x] Recherche de titres (endpoint serveur + cache)
- [x] Lecture de l'état du lecteur / titre en cours
- [x] Liste et sélection des appareils
- [x] Ajout d'un titre à la file Spotify
- [x] Passer au titre suivant
- [~] Rejouer ces appels contre la vraie API : recherche et lecture d'un titre validées en local ; les endpoints lecteur attendent un compte Premium connecté en OAuth

## Phase 3 — Cœur métier
- [x] Modèle de données : Host, Event, Guest, QueueItem, Vote
- [x] Création / démarrage / pause / clôture d'un événement
- [x] Génération du code court et du QR code (`GET /api/host/events/{code}/qr.svg`, SVG imprimable ; l'affichage côté organisateur reste en phase 7)
- [x] Session convive anonyme (pseudo + cookie signé)
- [x] Proposition d'un titre (doublons → upvote, quota par convive, délai avant rediffusion)
- [x] Votes up / down (unicité, changement, annulation)
- [x] Calcul du score et classement (épinglage, ancienneté)
- [x] Seuil de rejet automatique
- [x] Filtres : explicite, durée max
- [x] Paramètres d'événement

## Phase 4 — Worker de lecture « juste à temps »
- [x] Boucle de suivi par événement actif
- [x] Verrouillage et envoi du titre n°1 à T−15 s
- [x] Détection du changement de titre → mise à jour des statuts
- [x] Gestion des titres « hors file » (ajoutés directement dans Spotify)
- [x] Gestion pause / appareil déconnecté / token expiré
- [x] Gestion de la file vide
- [x] Reprise après redémarrage du serveur (tout l'état vit en base, le Scheduler relance la boucle)

## Phase 5 — Temps réel
- [x] Canal par événement
- [x] Diffusion : file, votes, titre en cours, statut de l'événement
- [x] Reconnexion automatique + resynchronisation de l'état
- [x] Indicateur de connexion côté client

## Phase 6 — Interface convive (mobile-first)
- [x] Écran « Rejoindre » (code / QR)
- [x] Saisie du pseudo
- [x] Écran principal : titre en cours + file
- [x] Boutons de vote avec retour visuel immédiat (optimistic UI)
- [x] Recherche avec debounce + résultats
- [x] Confirmation de proposition
- [x] « Mes galettes » (mes propositions)
- [x] États : vide, chargement, erreur, hors ligne, événement terminé/en pause, banni
- [~] Attribution Spotify dans l'interface convive (charte, `presentation.md` § 7.5) : lien « Écouter sur Spotify » en place ; reste le logo officiel, à télécharger depuis les ressources de design Spotify (il ne doit pas être redessiné)
- [ ] Relecture visuelle avec Alexandre sur mobile (parcours complet)

## Phase 7 — Interface organisateur
- [ ] Inscription / connexion
- [ ] Connexion du compte Spotify
- [ ] Liste et création d'événements
- [ ] Tableau de bord live : titre en cours, file, alertes
- [ ] Modération : retirer, épingler, passer
- [ ] Paramètres de l'événement
- [ ] Affichage / téléchargement du QR code

## Phase 8 — Qualité et mise en production
- [~] Tests unitaires (classement, règles de proposition, votes) : 41 tests / 119 assertions
- [~] Tests d'intégration (client Spotify mocké, worker) : parcours convive HTTP, domaine et worker de lecture couverts ; reste la couche Spotify elle-même (client, OAuth, rafraîchissement)
- [ ] Tests E2E des parcours convive et hôte
- [ ] Test de charge (300 convives simultanés)
- [ ] Test en conditions réelles (soirée test)
- [ ] Limitation de débit et anti-abus
- [ ] Mentions légales, politique de confidentialité, purge des données
- [ ] Hébergement, domaine, HTTPS
- [ ] Monitoring et logs d'erreurs
- [!] Demande d'extension de quota Spotify : hors de portée (organisations, 250 000 MAU minimum) — le produit reste plafonné à 5 comptes organisateurs

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
| 16/10/2026 | Le front convive n'ajoute aucune dépendance : machine à états maison plutôt qu'un routeur, `fetch` plutôt qu'une lib de data-fetching, animation de réordonnancement en FLIP via `Element.animate()`, `EventSource` avec backoff écrit à la main. |
| 16/10/2026 | Sans hub Mercure (cas de la prod aujourd'hui), le front retombe sur un rafraîchissement toutes les 10 s, plus une resynchronisation au retour d'onglet et au retour du réseau. |
| 16/10/2026 | Mercure 0.20 (`dunglas/mercure:latest`) refuse le paramètre `topic` et attend `match` ; les hubs ≤ 0.19 font l'inverse. Le client tente `match` puis bascule sur `topic` si la première connexion échoue, pour rester compatible avec le hub qui sera choisi en prod. |
| 16/10/2026 | Quota de propositions en attente porté de 3 à 5 pour coller aux maquettes (« 2 / 5 galettes en attente ») ; `presentation.md` aligné. |
| 16/10/2026 | Les convives arrivent sur `/e/CODE` (`?c=CODE` accepté) : une seule route courte à encoder dans le QR code. |
| 16/10/2026 | Les erreurs techniques Spotify ne sont jamais renvoyées aux convives (`spotifyUnreachable()` garde la cause dans les logs) ; les endpoints organisateur, eux, gardent le message exact. |
| 16/10/2026 | Une app Spotify en mode développement refuse `limit > 10` sur `/search` (« Invalid limit ») : la recherche est plafonnée à 10 résultats. À relever si la demande d'extension de quota aboutit. |
| 16/10/2026 | Changements Web API de février 2026 : `GET /me` ne renvoie plus `product` (ni `country`, ni `email`), les lectures groupées (`/tracks?ids=`) et `GET /markets` disparaissent, et `limit` de `/search` plafonne à 10. Le code ne lit aucun champ supprimé en dehors de `product`, dont l'absence vaut désormais « inconnu » et ne bloque plus la connexion de l'hôte : Spotify impose déjà Premium au propriétaire de l'app. |
| 19/10/2026 | `created_at` n'a qu'une seconde de précision : deux propositions faites dans la même seconde avec le même score pouvaient permuter d'une requête à l'autre. Le classement se termine désormais par l'UUIDv7, qui encode l'ordre de création. |
| 19/10/2026 | QR code rendu côté serveur en SVG avec `bacon/bacon-qr-code` (nouvelle dépendance) : l'image PHP n'embarque pas `ext-gd`, et un SVG s'imprime sans perte sur une affiche. Niveau de correction maximal (30 %) pour rester lisible de loin et à moitié masqué. |
| 19/10/2026 | Conditions Spotify vérifiées (§ 7.5 de `presentation.md`) : l'extension de quota est hors de portée (250 000 MAU), donc 5 comptes organisateurs maximum ; les convives ne comptent pas, ils ne s'authentifient jamais. L'app étant *non-streaming*, vendre l'accès reste autorisé, mais la charte impose le logo Spotify et un lien de retour vers chaque titre. |
| 27/10/2026 | En local, l'app passe sur les ports standards : `https://127.0.0.1` (le port 80 redirige vers 443). Elle reste en HTTPS, car le cookie convive est `Secure`. Redirect URI Spotify locale : `https://127.0.0.1/api/spotify/callback`. |
