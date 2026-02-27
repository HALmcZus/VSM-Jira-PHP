# VSM Jira PHP

<details>
<summary><strong>📑 Sommaire</strong></summary>

<!-- TOC -->
<!-- GitHub génère automatiquement le contenu -->
<!-- TOC -->

</details>


## 📑 Sommaire

- [🎯 Objectif du projet](#-objectif-du-projet)
- [🧠 Principes clés](#-principes-clés)
- [🏗️ Architecture](#️-architecture)
- [🧩 Cas d’usage principaux](#-cas-dusage-principaux)
  - [🔹 VSM par Version](#-vsm-par-version)
  - [🔹 VSM par Feature (à venir)](#-vsm-par-feature-à-venir)
- [📊 Métriques exposées](#-métriques-exposées)
- [🖥️ Frontend](#️-frontend)
- [🔐 Configuration](#-configuration)
- [🚀 Démarrer l'application](#-démarrer-lapplication)
- [🚧 État du projet](#-état-du-projet)
- [👤 Auteur](#-auteur)
- [⚠️ Disclaimer](#️-disclaimer)

## 🎯 Objectif du projet

**VSM Jira PHP** est une application web légère en PHP visant à produire des **Value Stream Maps (VSM) Lean** directement à partir des API **Jira**, considéré comme **source de vérité unique**.

L’objectif est de fournir une **vision factuelle, mesurable et exploitable** des flux de valeur (versions, features, issues) afin de :

* Visualiser les **délais réels** (Lead Time, Cycle Time)
* Identifier les **goulots d’étranglement**
* Alimenter les **discussions d’amélioration** continue au niveau Equipe et Train
* Soutenir les **pratiques Agile** / SAFe (Inspect & Adapt, flow metrics, etc.) avec des métriques issues des **pratiques Lean**.

---

## 🧠 Principes clés

* **Jira = source unique de données**
  Aucune donnée métier n’est saisie manuellement dans l’outil VSM.

* **Lecture seule**
  L’application ne modifie jamais Jira.

* **Approche orientée Use Cases**
  La logique métier est centralisée dans des UseCases explicites.

* **Séparation claire des responsabilités (MVC)**

* **Code lisible, maintenable, documenté et pédagogique**
  Le projet sert aussi de support de compréhension et d’évolution. Il est conçu pour être le plus simple et maintenable possible.

---

## 🏗️ Architecture

Le projet repose sur une architecture **PHP MVC** simple et volontairement explicite.
Il est développé en PHP ^8.5, framework Slim ^4.15 pour le Backend, et en HTML + Alpine.js pour le Frontend

```
├── config_files/
│   ├── jira_workflow.json          # Configuration du workflow Jira de votre projet (status correspondants aux phases d'affinage, de sprint et Done)
│   └── non_working_days.json.css   # Liste des jours non travaillés (fériés FR) --Sera remplacée par un appel API officielle des jours fériés français.
│
├── php/                   # Pour la version Standalone : moteur PHP portable (version 8.5)
│
├── public/
│   ├── index.php          # Front controller
│   └── style.css          # CSS global
│
├── src/
│   ├── Controller/        # Contrôleurs HTTP
│   ├── UseCase/           # Cas d’usage métier
│   ├── Model/             # Modèles métier (Version, Issue, Timeline…)
│   ├── Service/           # Accès Jira
│   └── View/              # Rendu des pages
│
├── test/                  # Répertoire des Tests Unitaires (PHPUnit)
│
├── .env                   # Credentials Jira et config sensibles
├── composer.json          # Liste des dépendances de librairies externes
├── start.bat              # Executable pour la version Standalone
└── README.md
```

---

## 🧩 Cas d’usage principaux

### 🔹 VSM par Version

> *Afficher une version Jira avec l’ensemble de ses issues et leurs timelines*

Use Case principal :

```
GetVersionWithIssuesAndTimelines
```

Responsabilités :

* récupération de la Version Jira
* récupération des issues associées
* construction des timelines (version + issues)
* calcul des métriques (Lead Time, moyennes, etc.)

---

### 🔹 VSM par Feature (à venir)

* basées sur les liens Jira (Epic / REP / Issues)
* navigation transverse par flux de valeur

---

## 📊 Métriques exposées

* **Lead Time** (jours calendaires entre la date de création et la date de passage à Done/Terminé)
* **Cycle Time** (jours ouvrés entre la date de passage à In progress et la date de passage à Done/Terminé)
* **Durée moyenne par statut**
* **Waiting times** basé sur les étiquettes (champ labels)
* Timelines consolidées (Version + Issues)

Les métriques sont calculées **à partir des dates Jira réelles**.

---

## 🖥️ Frontend

Le frontend est volontairement **léger et sans framework lourd**.

* **Alpine.js** pour la réactivité
* Communication via API JSON
* Gestion défensive de l’asynchrone
* Pas de logique métier côté client

---

## 🔐 Configuration

1. Les accès Jira et paramètres sensibles sont stockés dans un fichier `.env`, remplis avec des valeurs par défaut inexploitables (placeholders).

.env.template :
```
# Duplicate this file, rename it ".env" then replace these infos with yours
JIRA_BASE_URL=https://your-company.atlassian.net
JIRA_EMAIL=email@company.com
JIRA_API_TOKEN=your_api_token

# Disable SSL verification, set to true only for demo !
IS_DEMO=false
```

=> **Il faut modifier ces valeurs avec les vôtres (se rapprocher d'un Admin Jira si besoin). Sans ces informations, l'application ne peut pas communiquer avec les API Jira, et serait donc inutilisable.**

=> **Pour générer un token personnel API Jira** : https://id.atlassian.com/manage-profile/security/api-tokens (Doc officielle https://support.atlassian.com/atlassian-account/docs/manage-api-tokens-for-your-atlassian-account/#Create-an-API-token)


2. La pertinence des métriques basées sur les statuts Jira se base sur la déclaration de votre propre workflow Jira.

Celui-ci est à indiquer dans le fichier config_files\jira_workflow.json
***Note : il est important que les trois statuts Jira par défaut (To Do, In progress, Done) soient indiqués, même s'ils sont traduits ou si vous ne les utilisez pas***

Exemple :
```
{
    "refinement_statuses": [
        "Backlog",
        "En rédaction",
        "A affiner",
        "Affinée",
        "Stratégie de Qualif",
        "Vainci 1",
        "A planifier"
    ],
    "sprint_statuses": [
        "To Do",
        "À faire",
        "In Progress",
        "Revue Dév",
        "A qualifier",
        "Qualif En Cours"
        "Validation PO",
        "Vainci 2"
    ],
    "done_statuses": [
        "Done",
        "Terminé(e)",
        "abandonné"
    ]
}
```

3. *En attendant l'implémentation de l'appel à l'API officielle des jours fériés en France :*

La métrique "Cycle Time" étant mesurée en jours _ouvrés_, il faut également indiquer les jours fériés de l'année en cours et de l'année N-1.

Cela se fait dans le fichier config_files\non_working_days.json

Exemple :
```
{
    "non_working_days": {
        "Y-1 Jour de l'An" : "2025-01-01",
        "Y-1 Lundi de Pâques" : "2025-04-21",
        "Y-1 Fête du Travail" : "2025-05-01",
        "Y-1 Victoire 1945" : "2025-05-08",
        "Y-1 Ascension" : "2025-05-29",
        "Y-1 Lundi de Pentecôte" : "2025-06-09",
        "Y-1 Fête Nationale" : "2025-07-14",
        "Y-1 Assomption" : "2025-08-15",
        "Y-1 Toussaint" : "2025-11-01",
        "Y-1 Armistice 1918" : "2025-11-11",
        "Y-1 Noël" : "2025-12-25",

        "Jour de l'An" : "2026-01-01",
        "Lundi de Pâques" : "2026-04-06",
        "Fête du Travail" : "2026-05-01",
        "Victoire 1945" : "2026-05-08",
        "Ascension" : "2026-05-14",
        "Lundi de Pentecôte" : "2026-05-25",
        "Fête Nationale" : "2026-07-14",
        "Assomption" : "2026-08-15",
        "Toussaint" : "2026-11-01",
        "Armistice 1918" : "2026-11-11",
        "Noël" : "2026-12-25"
    }
}
```

---

## 🚀 Démarrer l'application

Après avoir configuré le projet (cf point précédent), il suffit de double-cliquer sur le fichier **start.bat**.

Celui-ci démarre automatiquement le serveur PHP embarqué et ouvre l'appli dans votre navigateur ([http://localhost:8080/](http://localhost:8080/))

***Si vous n'avez pas le serveur PHP embarqué, téléchargez php en version 8.5 et mettez le dossier php dans le dossier de l'application, puis vérifiez le chemin de php.exe dans start.bat***


---

## 🚧 État du projet

Projet **en cours de développement**.

Axes d’évolution :

* VSM par Feature
* Métriques du Sprint en cours
* Métriques de l'équipe
* Historique
* et bien d'autres idées pouvant être utiles à un Scrum Master et à son équipe 😉

---

## 👤 Auteur

[Hugues-Arnaud Lamot](https://www.linkedin.com/in/hugues-arnaud-lamot/) *Scrum Master, et ancien Lead Dev PHP*

---

## ⚠️ Disclaimer

Ce projet n’est **pas un produit officiel Atlassian** et n’a aucune affiliation avec Jira.

---
