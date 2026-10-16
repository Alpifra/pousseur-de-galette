# Pousseur de Galette — Cahier des charges

> Version 0.1 — 16/10/2026 — Document de cadrage, à valider avant le démarrage du développement.

---

## 1. Contexte et vision

Lors d'un mariage, d'un anniversaire ou d'une soirée privée, la musique est soit gérée par un DJ, soit par une playlist figée, soit par « celui qui a le téléphone branché sur l'enceinte ». Les convives n'ont aucun moyen simple et équitable d'influencer ce qui passe.

**Pousseur de Galette** est une web app qui transforme la playlist de la soirée en **file d'attente collaborative** :

- les convives rejoignent l'événement en scannant un QR code, **sans installer d'application ni créer de compte** ;
- ils **recherchent** un titre (catalogue Spotify) et le **proposent** ;
- chacun peut **voter pour (upvote)** ou **contre (downvote)** les titres en attente ;
- la file se **réordonne en temps réel** selon les votes ;
- le titre en tête de file est **poussé automatiquement au compte Spotify « maître »** de l'organisateur, qui diffuse la musique.

---

## 2. Objectifs

| Objectif | Indicateur de réussite |
|---|---|
| Rejoindre un événement en moins de 15 s | Scan QR → saisie d'un pseudo → accès à la file |
| Proposer un titre en moins de 3 interactions | Recherche → sélection → confirmation |
| Une file toujours à jour | Propagation d'un vote à tous les convives < 1 s |
| Aucune coupure musicale | Le titre suivant est toujours transmis à Spotify avant la fin du titre en cours |
| Garder le contrôle côté organisateur | Modération possible à tout moment (retirer, épingler, passer, bannir) |

---

## 3. Acteurs

| Acteur | Description | Authentification |
|---|---|---|
| **Organisateur (hôte)** | Crée l'événement, connecte son compte Spotify Premium, modère la file. | Compte Pousseur de Galette + OAuth Spotify |
| **Convive** | Rejoint l'événement, recherche, propose et vote. | Aucune : pseudo + session anonyme liée au navigateur |
| **Écran de diffusion** *(optionnel)* | Affichage TV / vidéoprojecteur : titre en cours, file, QR code pour rejoindre. | Lien secret en lecture seule |

---

## 4. Parcours utilisateurs

### 4.1 Organisateur

1. Se connecte à la plateforme puis relie son compte **Spotify Premium** (OAuth).
2. Crée un événement : nom, date, paramètres (voir § 6.4).
3. Obtient un **QR code** et un **code court** (ex. `GALET-4821`) à afficher ou imprimer.
4. Lance la lecture sur l'appareil Spotify de son choix (enceinte, PC, téléphone).
5. Démarre l'événement : la plateforme prend le relais pour alimenter la file Spotify.
6. Modère pendant la soirée depuis son tableau de bord.
7. Clôture l'événement → récapitulatif (titres joués, plus votés) et possibilité d'**exporter la soirée en playlist Spotify**.

### 4.2 Convive

1. Scanne le QR code (ou saisit le code court).
2. Choisit un pseudo.
3. Voit le **titre en cours** et la **file d'attente** classée.
4. Vote 👍 / 👎 sur les titres en attente.
5. Recherche un titre et le propose ; s'il est déjà dans la file, sa proposition devient un upvote.
6. Suit ses propositions (« Mes galettes ») et est notifié quand l'une d'elles passe.

---

## 5. Périmètre fonctionnel

### 5.1 MVP

**Côté convive**
- Rejoindre un événement via QR code / code court + pseudo.
- Afficher le titre en cours (pochette, titre, artiste, progression).
- Afficher la file classée avec score et vote personnel.
- Upvote / downvote (un vote par titre, modifiable ou annulable).
- Recherche Spotify (titres) avec résultats en direct.
- Proposer un titre.
- Mise à jour temps réel de la file.

**Côté organisateur**
- Création de compte / connexion.
- Liaison OAuth Spotify (Premium requis).
- Création, démarrage, pause, clôture d'un événement.
- Choix de l'appareil Spotify de diffusion.
- Tableau de bord : titre en cours, file, actions de modération (retirer, épingler en tête, passer le titre en cours).
- Paramètres de l'événement (§ 6.4).
- QR code téléchargeable / imprimable.

### 5.2 V2 (après MVP)
- Écran de diffusion (mode TV).
- Bannir un convive.
- Playlist de secours (« fallback ») quand la file est vide.
- Export de la soirée en playlist Spotify.
- Récapitulatif de fin de soirée (top titres, top convives).
- Notifications (titre proposé qui passe).
- Liste de titres / artistes interdits définie par l'hôte.
- « Moments » réservés (ex. première danse, gâteau) : l'hôte injecte un titre précis à une heure donnée.
- PWA installable.
- Internationalisation (FR / EN).

### 5.3 Hors périmètre
- Lecture audio dans la plateforme elle-même (c'est Spotify qui diffuse).
- Support d'autres services de streaming (Deezer, Apple Music…) — à envisager plus tard via une couche d'abstraction.
- Application mobile native.
- Monétisation / paiement.

---

## 6. Règles métier

### 6.1 Score et classement
- **Score = nombre d'upvotes − nombre de downvotes.**
- La proposition initiale compte comme un upvote du proposant.
- Classement : score décroissant, puis **ancienneté de la proposition** (premier proposé, premier servi à score égal).
- Un titre **épinglé** par l'hôte passe devant tous les autres, quel que soit son score.

### 6.2 Votes
- Un convive = un vote par titre (👍, 👎 ou neutre). Il peut changer d'avis tant que le titre n'est pas verrouillé.
- **Seuil de rejet** : si le score atteint une valeur paramétrable (ex. −5), le titre est retiré automatiquement.

### 6.3 Propositions
- Un titre déjà présent dans la file ne peut pas être reproposé : la tentative devient un upvote.
- Un titre joué depuis moins de X minutes (paramétrable) ne peut pas être reproposé.
- Nombre maximal de propositions **en attente** par convive (paramétrable, ex. 3) pour éviter qu'une seule personne monopolise la file.
- Filtres optionnels : contenus explicites, durée maximale d'un titre.

### 6.4 Paramètres d'un événement
| Paramètre | Valeur par défaut |
|---|---|
| Autoriser les downvotes | Oui |
| Seuil de rejet automatique | −5 |
| Propositions en attente max / convive | 3 |
| Délai avant de rejouer un titre | 120 min |
| Bloquer les titres explicites | Non |
| Durée max d'un titre | 10 min |
| Validation manuelle des propositions par l'hôte | Non |

### 6.5 Verrouillage du titre suivant
Pour éviter les coupures, le titre en tête de file est **transmis à Spotify quelques secondes avant la fin du titre en cours** (voir § 7.3). À partir de ce moment, il est **verrouillé** : il ne peut plus être dépassé ni recevoir de votes, et il est affiché comme « À suivre ».

---

## 7. Intégration Spotify

### 7.1 Authentification
- **OAuth 2.0 Authorization Code (avec PKCE)**, réalisé uniquement par l'organisateur.
- Scopes nécessaires :
  - `user-read-playback-state` — lire l'état du lecteur et les appareils ;
  - `user-modify-playback-state` — ajouter à la file, passer, lancer la lecture ;
  - `user-read-currently-playing` — titre en cours ;
  - `playlist-modify-private` *(V2)* — export de la soirée en playlist.
- Les tokens (access + refresh) sont **stockés chiffrés côté serveur** et ne transitent jamais vers les navigateurs des convives.
- Les convives n'ont **pas** besoin de compte Spotify : leurs recherches passent par le serveur.

### 7.2 Endpoints utilisés (Spotify Web API)
| Usage | Endpoint |
|---|---|
| Recherche | `GET /v1/search?type=track` |
| Titre en cours / progression | `GET /v1/me/player/currently-playing` |
| État du lecteur, appareil actif | `GET /v1/me/player` |
| Liste des appareils | `GET /v1/me/player/devices` |
| Ajouter à la file Spotify | `POST /v1/me/player/queue` |
| Passer au titre suivant | `POST /v1/me/player/next` |
| Lancer / reprendre la lecture | `PUT /v1/me/player/play` |

### 7.3 Contrainte majeure : la file Spotify n'est pas modifiable
L'API Spotify permet d'**ajouter** un titre à la file, mais **pas d'en retirer ni de la réordonner**. Pousser toute la file d'un coup rendrait les votes inopérants.

**Stratégie retenue : alimentation « juste à temps »**
1. La file de référence est celle de Pousseur de Galette (base de données), pas celle de Spotify.
2. Un worker serveur suit la lecture (`currently-playing`), toutes les ~3 à 5 s.
3. Quand il reste **≤ 15 s** (paramétrable) sur le titre en cours, le titre n°1 de la file est verrouillé puis envoyé via `POST /me/player/queue`.
4. Quand le changement de titre est détecté, le titre poussé passe en « En cours » et le cycle recommence.
5. **Cas limites à gérer** :
   - l'hôte passe un titre manuellement dans Spotify → resynchronisation ;
   - l'hôte ajoute des titres directement dans Spotify → ces titres sont joués, la plateforme les affiche comme « hors file » ;
   - lecture en pause / appareil déconnecté → alerte sur le tableau de bord ;
   - file vide → playlist de secours (V2) ou laisser Spotify continuer (autoplay) ;
   - token expiré → rafraîchissement automatique.

### 7.4 Limites et points de vigilance
- **Spotify Premium obligatoire** pour le compte maître (les endpoints de contrôle du lecteur ne fonctionnent pas en Free).
- **Rate limiting** (HTTP 429 + `Retry-After`) : debounce de la recherche côté client (~300 ms), cache serveur des résultats de recherche, un seul worker de suivi par événement.
- **Mode développement Spotify** : une app Spotify en mode développement est limitée à un petit nombre d'utilisateurs autorisés (les organisateurs, pas les convives). Une ouverture publique nécessite une demande d'extension de quota auprès de Spotify. **Conditions actuelles à vérifier sur le Developer Dashboard avant le lancement.**
- **Conditions d'utilisation et charte Spotify** : obligation d'afficher l'attribution Spotify (logo / lien vers le titre), de ne pas altérer les pochettes, et de **ne pas laisser croire que l'app est un produit Spotify**. La DA « proche de Spotify » (§ 9) doit donc rester une inspiration : nom, logo et couleur d'accent propres. **À relire avant la mise en production, notamment pour tout usage commercial.**

---

## 8. Architecture technique (proposition à valider)

### 8.1 Vue d'ensemble

```
┌───────────────┐   HTTPS + temps réel    ┌──────────────────────┐   Web API    ┌──────────┐
│ Convives      │ ◀─────────────────────▶ │ Application web      │ ───────────▶ │ Spotify  │
│ (mobile web)  │                          │ (front + API)        │              └──────────┘
├───────────────┤                          │                      │                   ▲
│ Hôte          │ ◀─────────────────────▶ │ Worker de lecture    │ ──────────────────┘
│ (dashboard)   │                          │ (1 boucle / événement)│
└───────────────┘                          └──────────┬───────────┘
                                                      │
                                              ┌───────▼────────┐
                                              │ Base de données │
                                              └────────────────┘
```

### 8.2 Stack retenue

| Couche | Technologie |
|---|---|
| API | Symfony 8.1 + API Platform 5 (PHP 8.5) |
| Front | React 19 + TypeScript + Vite |
| Base de données | PostgreSQL 18 + Doctrine ORM |
| Temps réel | Mercure (intégration native API Platform) |
| Worker de lecture | Symfony Messenger + Scheduler |
| Infra locale | Docker Compose : Caddy, PHP-FPM, worker, Mercure, PostgreSQL, Node |

### 8.2 bis Options étudiées initialement

| Option | Composition | Avantages | Inconvénients |
|---|---|---|---|
| **A — Next.js full-stack** *(recommandée)* | Next.js (App Router) + TypeScript, Tailwind, PostgreSQL + Prisma/Drizzle, temps réel via WebSocket (Socket.IO) ou service managé (Pusher/Ably/Supabase Realtime), worker Node séparé | Un seul langage, front + API au même endroit, écosystème riche, déploiement simple | Les WebSockets ne tournent pas en serverless pur (Vercel) → serveur Node dédié ou service temps réel managé |
| **B — Nuxt full-stack** | Nuxt 3 + Nitro (WebSocket natif), PostgreSQL | Équivalent Vue, WebSocket intégré à Nitro | Écosystème un peu plus restreint |
| **C — Laravel + front SPA** | Laravel (API, queues, scheduler, Reverb pour le temps réel) + React/Vue via Inertia | Queues et scheduler natifs, idéal pour le worker de lecture | Deux langages (PHP + JS), hébergement plus classique |
| **D — Supabase + front** | Supabase (Postgres, Realtime, Edge Functions) + front React/Vue | Temps réel et auth prêts à l'emploi, très rapide à prototyper | Dépendance à un tiers, logique du worker moins naturelle en Edge Functions |

### 8.3 Exigences de l'architecture
- La logique « juste à temps » (§ 7.3) doit tourner dans un **processus serveur persistant** (worker / job planifié), indépendant des navigateurs.
- Le temps réel doit pouvoir être diffusé par « salle » (un canal par événement).
- Le calcul du classement doit être fait côté serveur (source de vérité unique).

---

## 9. Direction artistique (résumé)

- **Inspiration : interface de Spotify** — thème sombre, surfaces gris anthracite, pochettes mises en avant, typographie sans-serif grasse, boutons arrondis « pilule ».
- **Seule différence majeure : la couleur d'accent**. Le vert Spotify (`#1DB954`) est remplacé par une couleur propre à Pousseur de Galette (proposition : **orange « Galette » `#FF7A1A`**, alternatives dans `maquette.md`).
- **Mobile-first** pour les convives ; tableau de bord hôte utilisable sur mobile et desktop.
- Le brief détaillé pour la maquette se trouve dans [`maquette.md`](./maquette.md).

---

## 10. Modèle de données (première ébauche)

| Entité | Champs principaux |
|---|---|
| `Host` | id, email, mot de passe / provider, spotify_user_id, spotify_tokens (chiffrés), created_at |
| `Event` | id, host_id, name, date, join_code, status (`draft`/`live`/`paused`/`ended`), settings (JSON), spotify_device_id, created_at |
| `Guest` | id, event_id, pseudo, session_token, banned, created_at |
| `QueueItem` | id, event_id, spotify_track_id, title, artist, album_art_url, duration_ms, explicit, proposed_by (guest_id), status (`pending`/`locked`/`playing`/`played`/`removed`/`rejected`), pinned, score (dénormalisé), created_at, played_at |
| `Vote` | id, queue_item_id, guest_id, value (+1 / −1), updated_at — unique (queue_item_id, guest_id) |

---

## 11. Exigences non fonctionnelles

- **Performance** : file affichée < 2 s sur réseau 4G moyen ; propagation des votes < 1 s.
- **Charge cible** : 300 convives connectés simultanément par événement, plusieurs événements en parallèle.
- **Réseau dégradé** (salles de réception souvent mal couvertes) : reconnexion automatique du temps réel, interface légère, états hors ligne explicites.
- **Compatibilité** : Safari iOS et Chrome Android des 2 dernières versions majeures ; desktop récent pour l'hôte.
- **Accessibilité** : contrastes WCAG AA, cibles tactiles ≥ 44 px, navigation clavier sur le dashboard.
- **RGPD** : aucune donnée personnelle demandée aux convives (pseudo uniquement), purge des données convives X jours après la clôture de l'événement, mentions légales et politique de confidentialité.

---

## 12. Sécurité et anti-abus

- Tokens Spotify chiffrés au repos, jamais exposés au client.
- Code d'événement suffisamment long pour ne pas être deviné ; possibilité de le régénérer.
- Session convive signée (cookie httpOnly) ; un vote par session et par titre.
- Limitation de débit sur les votes, propositions et recherches (par session et par IP).
- Filtrage des pseudos (longueur, caractères, liste de mots interdits).
- Actions de modération réservées à l'hôte, vérifiées côté serveur.

> Limite connue : un convive peut ouvrir plusieurs navigateurs pour voter plusieurs fois. Acceptable pour un événement privé ; le bannissement par l'hôte sert de garde-fou.

---

## 13. Risques

| Risque | Impact | Mitigation |
|---|---|---|
| Changement des conditions / quotas de l'API Spotify | Bloquant | Vérifier les conditions dès le démarrage, abstraire la couche Spotify |
| Coupure entre deux titres (titre suivant poussé trop tard) | Fort | Marge de 15 s, worker robuste, alertes hôte |
| Mauvais réseau sur le lieu de l'événement | Fort | UI légère, reconnexion auto, mode dégradé |
| Hôte qui manipule Spotify en parallèle | Moyen | Resynchronisation et affichage « hors file » |
| Spam / trolling des convives | Moyen | Quotas, seuil de rejet, bannissement |
| Confusion avec la marque Spotify | Moyen | Nom, logo et couleur propres, respect de la charte Spotify |

---

## 14. Questions ouvertes

1. ~~**Stack technique**~~ → Symfony / API Platform + React (§ 8.2).
2. **Couleur d'accent** : valider l'orange `#FF7A1A` ou une alternative.
3. **Comptes organisateurs** : compte classique (email + mot de passe), connexion directe via Spotify, ou les deux ?
4. **Hébergement** cible (VPS, Vercel + service temps réel, Railway/Fly.io…) ?
5. **Modèle** : projet personnel / usage privé, ou service ouvert au public (impact sur le quota Spotify et les conditions d'utilisation) ?
6. **Validation manuelle** des propositions : utile dès le MVP ?
7. **Durée de conservation** des données après un événement ?
