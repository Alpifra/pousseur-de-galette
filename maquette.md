# Brief de maquettage — Pousseur de Galette

> Brief destiné à Claude Design. Merci de produire les maquettes des écrans listés au § 5, en respectant la direction artistique du § 3.

---

## 1. Le produit en une phrase

**Pousseur de Galette** est une web app pour mariages, anniversaires et soirées privées : les convives scannent un QR code, recherchent des titres, les proposent et votent 👍 / 👎 pour construire ensemble la file d'attente musicale, qui est diffusée automatiquement par le compte Spotify de l'organisateur.

*« Galette » = disque vinyle en argot : le convive « pousse sa galette » pour qu'elle passe.*

---

## 2. Utilisateurs et contextes d'usage

| Utilisateur | Appareil | Contexte |
|---|---|---|
| **Convive** | Smartphone (principalement) | Debout, dans une salle sombre et bruyante, souvent un verre à la main, parfois avec un réseau faible. Usage par sessions très courtes (10–30 s). |
| **Organisateur** | Smartphone et desktop | Prépare l'événement en amont (desktop) puis surveille / modère pendant la soirée (smartphone). |
| **Écran de diffusion** | TV / vidéoprojecteur | Visible de loin par toute la salle. |

Conséquences pour le design : **très lisible, gros éléments tactiles, usage à une main, contrastes forts, aucune friction**.

---

## 3. Direction artistique

### 3.1 Principe
S'inspirer **fortement de l'interface de Spotify** (desktop et mobile) : thème sombre, pochettes d'album mises en avant, hiérarchie typographique marquée, boutons « pilule », listes de titres compactes.

**La seule différence voulue : la couleur d'accent.** Le vert Spotify `#1DB954` ne doit **jamais** apparaître. Il est remplacé par la couleur d'accent ci-dessous partout où Spotify utilise son vert (bouton principal, état actif, progression, icônes sélectionnées, titre en cours).

> ⚠️ L'app ne doit pas pouvoir être confondue avec un produit Spotify : logo, nom et couleur d'accent propres. Le logo Spotify n'apparaît que comme attribution (« Propulsé par Spotify », lien « Écouter sur Spotify »), conformément à la charte Spotify. Les pochettes ne doivent pas être recadrées de façon déformante ni recouvertes.

### 3.2 Couleur d'accent

**Proposition principale : Orange « Galette » `#FF7A1A`** — chaleureuse, festive, évoque la galette dorée et le vinyle sous les spots.

Merci de présenter aussi le rendu de l'écran principal convive avec ces alternatives pour comparaison :

| Nom | Hex | Intention |
|---|---|---|
| Orange Galette *(principale)* | `#FF7A1A` | Chaleureux, festif |
| Fuchsia Dancefloor | `#FF3D9A` | Soirée, énergie |
| Violet Néon | `#9B5CFF` | Nocturne, club |
| Ambre Disco | `#FFB21A` | Doré, élégant (mariage) |

Prévoir pour la couleur retenue : une variante **hover/pressed** (plus claire), une variante **atténuée** (fond de badge à ~15 % d'opacité). Le texte posé sur l'accent est **noir** (comme sur le vert Spotify).

### 3.3 Palette neutre (reprise de Spotify)

| Token | Valeur | Usage |
|---|---|---|
| `bg-base` | `#121212` | Fond principal |
| `bg-elevated` | `#181818` | Cartes, listes |
| `bg-highlight` | `#282828` | Survol, éléments sélectionnés |
| `bg-press` | `#000000` | Barre de navigation, lecteur |
| `text-primary` | `#FFFFFF` | Titres, texte principal |
| `text-secondary` | `#B3B3B3` | Artistes, métadonnées |
| `text-muted` | `#6A6A6A` | Désactivé |
| `danger` | `#F15E6C` | Downvote, suppression, erreurs |
| `border` | `#2A2A2A` | Séparateurs discrets |

Dégradés : comme Spotify, un **dégradé vertical** en haut de l'écran principal, coloré à partir de la pochette du titre en cours (ou de l'accent par défaut), qui se fond dans `bg-base`.

### 3.4 Typographie
Spotify utilise *Circular* (propriétaire). Utiliser une alternative libre au caractère proche : **Figtree** ou **Plus Jakarta Sans** (Google Fonts).
- Titres d'écran : 28–32 px, gras (700–800).
- Titre de morceau : 16 px, medium/semi-bold.
- Artiste / métadonnées : 14 px, regular, `text-secondary`.
- Labels / badges : 12 px, gras, éventuellement en capitales espacées.

### 3.5 Formes et composants
- Boutons principaux : **pilule** (rayon complet), fond accent, texte noir, gras.
- Boutons secondaires : pilule à contour blanc / gris.
- Pochettes : rayon 4–8 px ; grande pochette arrondie sur le « titre en cours ».
- Lignes de titre façon Spotify : pochette 48 px + titre + artiste + actions à droite.
- Cibles tactiles **≥ 44 px**.
- Icônes : style linéaire, épaisseur homogène (type Lucide / Phosphor).

### 3.6 Motion
- Réordonnancement de la file **animé** (les titres glissent à leur nouvelle position quand les votes changent) — c'est le cœur de l'expérience, il doit être visible et satisfaisant.
- Vote : micro-animation (rebond de l'icône + incrément du score).
- Titre poussé à Spotify : transition vers le bloc « À suivre ».

---

## 4. Logo et identité
- Nom : **Pousseur de Galette**.
- Proposer un **logotype simple** et une **icône** (favicon / app icon) : piste suggérée — un disque vinyle stylisé, éventuellement avec une flèche vers le haut (upvote) ou une main qui le pousse.
- Ton : festif, complice, jamais vulgaire. Tutoiement côté convive (« Propose ta galette », « Ta galette passe dans 2 titres ! »).

---

## 5. Écrans à maquetter

### 5.1 Convive — mobile (390 × 844, prioritaire)

1. **Rejoindre**
   - Arrivée via QR code (code déjà renseigné) ou saisie manuelle d'un code court (`GALET-4821`).
   - Nom de l'événement (« Mariage de Léa & Sam ») et visuel festif.
2. **Choix du pseudo**
   - Un champ, un bouton « C'est parti ». Rien d'autre.
3. **Écran principal — La file** *(écran le plus important)*
   - En haut : **titre en cours** (grande pochette, titre, artiste, barre de progression en couleur d'accent, temps restant).
   - Bloc **« À suivre »** : le titre verrouillé, déjà envoyé à Spotify (non votable, badge « Verrouillé »).
   - **File d'attente** classée : rang, pochette, titre, artiste, pseudo du proposant, **score**, boutons 👍 / 👎 avec état actif (accent pour 👍, `danger` pour 👎).
   - Titres épinglés par l'hôte : badge « Choix de l'hôte ».
   - Mise en avant discrète des titres proposés par l'utilisateur.
   - Bouton flottant ou barre basse : **« Proposer une galette »** (recherche).
   - Indicateur de connexion temps réel (discret).
4. **Recherche**
   - Champ de recherche plein écran, focus automatique.
   - Résultats en direct : pochette, titre, artiste, durée, badge « E » si explicite.
   - États par résultat : « Proposer », « Déjà dans la file → +1 », « Joué récemment » (désactivé), « Bloqué par l'hôte » (désactivé).
   - Compteur de propositions restantes (« 2 / 3 galettes en attente »).
5. **Confirmation de proposition**
   - Bottom sheet : pochette, titre, position estimée dans la file, bouton « Pousser la galette ».
   - Toast de succès.
6. **Mes galettes**
   - Liste de mes propositions avec leur statut : en attente (rang + score), à suivre, jouée, retirée.

**États à prévoir** (au moins une maquette chacun) :
- File vide (« Personne n'a encore poussé de galette… sois le premier ! »).
- Chargement (skeletons façon Spotify).
- Recherche sans résultat.
- Hors ligne / reconnexion en cours.
- Événement en pause / terminé.
- Quota de propositions atteint.
- Convive banni.

### 5.2 Organisateur — desktop (1440 × 900) et mobile

7. **Connexion / inscription**.
8. **Connexion Spotify** : explication (compte Premium requis), bouton « Connecter Spotify », état connecté (avatar + nom du compte).
9. **Mes événements** : liste (à venir, en cours, terminés) + « Créer un événement ».
10. **Création / paramètres d'un événement** : nom, date, et réglages :
    - autoriser les downvotes (switch) ;
    - seuil de rejet automatique (nombre) ;
    - propositions en attente max par convive ;
    - délai avant rediffusion d'un titre ;
    - bloquer les titres explicites (switch) ;
    - durée max d'un titre ;
    - validation manuelle des propositions (switch).
11. **QR code** : grand QR code + code court + boutons « Télécharger », « Imprimer », « Ouvrir l'écran de diffusion ». Prévoir un **visuel imprimable A5** (carton de table de mariage) avec le QR code.
12. **Tableau de bord live** *(desktop + version mobile)* :
    - lecteur : titre en cours, appareil Spotify actif (sélecteur), boutons « Passer », pause/lecture ;
    - statut : en direct / en pause, nombre de convives connectés ;
    - file avec actions de modération par titre : **épingler en tête**, **retirer**, (V2) **bannir le proposant** ;
    - file des propositions à valider si la validation manuelle est activée ;
    - **alertes** : appareil Spotify déconnecté, lecture en pause, token Spotify expiré, file vide ;
    - bouton « Clôturer l'événement ».
13. **Récapitulatif de fin** *(V2)* : nombre de titres joués, top 5 des titres les plus votés, top proposants, bouton « Exporter en playlist Spotify ».

### 5.3 Écran de diffusion — TV (1920 × 1080, V2)
14. Titre en cours en très grand (pochette + dégradé coloré), « À suivre », top 5 de la file avec scores, **QR code permanent** dans un coin (« Scanne et pousse ta galette »). Lisible à 5 mètres.

---

## 6. Composants à livrer (design system)
- Boutons (primaire, secondaire, icône, tailles, états : défaut, hover, pressed, désactivé, chargement).
- Ligne de titre (variantes : file votable, à suivre/verrouillé, résultat de recherche, modération hôte).
- Bloc « titre en cours » (mobile, desktop, TV).
- Boutons de vote avec états (neutre, upvoté, downvoté) et score.
- Badges : Verrouillé, Choix de l'hôte, Explicite, Joué récemment, Ta galette.
- Champ de recherche, champ texte, switch, champ numérique.
- Bottom sheet, toast, modale de confirmation.
- Bannière d'alerte (info, avertissement, erreur).
- Skeletons de chargement.
- Indicateur de connexion temps réel.

---

## 7. Livrables attendus
1. Planche de **design tokens** (couleurs, typographies, rayons, espacements, ombres).
2. **Comparatif des 4 couleurs d'accent** sur l'écran principal convive.
3. Maquettes haute fidélité des écrans du § 5 (mobile convive en priorité, puis organisateur, puis TV).
4. Les **états** listés au § 5.1.
5. Bibliothèque de **composants** du § 6.
6. Si possible : un **prototype cliquable** du parcours convive (rejoindre → voter → rechercher → proposer).

## 8. Contraintes
- Mobile-first, une main, pouce.
- Contrastes **WCAG AA** minimum (vérifier particulièrement le texte sur la couleur d'accent).
- Pas de mode clair (thème sombre uniquement, comme Spotify).
- Contenus de démonstration réalistes : titres variés (variété française, pop, classiques de mariage), pseudos crédibles (« Tonton Gégé », « Léa 💍 », « Julie », « Le cousin DJ »).
