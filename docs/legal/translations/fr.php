<?php

return [
    'header' => <<<'MD'
**Statut : projet non validé, en attente d’un examen spécialisé et, le cas échéant, d’une vérification par un avocat.**
**Version du document : draft-1.0 · Langue : Français.**
**Exploitant :** [REQUIRED: nom juridique complet, adresse et coordonnées].
**Territoire contractuel :** [REQUIRED: pays effectivement ouverts et informations destinées aux consommateurs requises pour chaque pays].

Ce projet décrit exclusivement les fonctionnalités effectivement mises à disposition. Il ne garantit pas la validité juridique du texte. Sa publication comme texte contractuel applicable exige une validation spécialisée documentée. Les informations sur l’exploitant, les pratiques commerciales et les interfaces de commande effectivement utilisées doivent correspondre.
MD,
    'documents' => [
        'terms' => [
            'title' => 'Conditions générales',
            'provisions' => <<<'MD'
## 1. Parties au contrat et champ d’application
Les présentes conditions régissent l’accès à la plateforme technique et les services de la plateforme expressément commandés. L’exploitant sert d’intermédiaire pour les offres des utilisateurs. En principe, l’acheteur et le vendeur concluent entre eux le contrat de vente du bien. La plateforme ne devient de ce fait ni propriétaire ni vendeuse, et ne prend pas automatiquement en charge l’expédition, la remise ou la garde du bien. Les éventuelles obligations supplémentaires de l’exploitant liées à une fonctionnalité dépendent de son fonctionnement effectif et des règles de droit impératives.

## 2. Compte, offres et conclusion du contrat
Les données du compte doivent être exactes. Les identifiants de connexion doivent rester confidentiels. Tout accès suspect doit être signalé sans délai. Les vendeurs peuvent proposer uniquement des biens courants licites et disponibles. Ils doivent indiquer fidèlement leur état, leur prix, les conditions de livraison et leur éventuel statut de professionnel. La conclusion des contrats d’enchères et de vente à prix fixe est régie par les conditions propres à ces offres. L’activation d’une catégorie ou d’une vérification d’identité ne vaut pas autorisation légale générale de commercialisation.

## 3. Services, modifications et cessation
Avant la commande, l’étendue, le prix total et la durée des services payants sont affichés et enregistrés avec leur version. Les règles de disponibilité, de modération et de paiement ne peuvent être modifiées rétroactivement de façon dissimulée. En cas de modification substantielle du contrat, il faut vérifier si une information ou un nouvel accord exprès s’impose. Le seul fait de continuer à utiliser la plateforme ne remplace pas une déclaration exigée par la loi. Les utilisateurs peuvent demander la désactivation de leur compte et l’examen de leur demande d’effacement au regard du droit de la protection des données.

## 4. Responsabilités et responsabilité civile
La plateforme doit respecter ses propres obligations légales de vérification, d’information et de réaction. Aucune exclusion illicite de responsabilité ne s’applique en cas de faute intentionnelle, de faute lourde, d’atteinte à la vie, à l’intégrité physique ou à la santé, ni lorsque la responsabilité est impérative, notamment au titre de la loi allemande sur la responsabilité du fait des produits défectueux (Produkthaftungsgesetz) ou des garanties assumées. En cas de manquement par négligence légère à une obligation contractuelle essentielle, la responsabilité ne peut être limitée au dommage prévisible et typique du contrat que dans la mesure autorisée par la loi. L’obligation d’un utilisateur de garantir l’exploitant suppose une violation du droit imputable à cet utilisateur et des demandes fondées. La faute contributive et les moyens de défense restent pris en compte.

## 5. Vérification avant publication
[REQUIRED: faire examiner le droit applicable, la clause de compétence territoriale admissible entre professionnels, les informations destinées aux consommateurs, la communication contractuelle et la description précise des services.]
MD,
        ],
        'private-use' => [
            'title' => 'Conditions d’utilisation applicables aux particuliers',
            'provisions' => <<<'MD'
## Compte et utilisation autorisée
Les particuliers agissent à des fins privées qui leur sont propres. Une activité professionnelle ne peut être déguisée en vente entre particuliers. Les noms de compte ne peuvent porter atteinte aux droits de tiers ni faire croire à une vérification officielle ou à une vérification qui n’a pas eu lieu. Les données obligatoires du compte doivent être à jour. Les documents d’identité qui ne sont pas requis ne doivent pas être envoyés dans une annonce publique ou un message privé.

## Offres et prise de contact
Les offres doivent décrire clairement les biens effectivement disponibles. Le prix, l’état, le retrait, les frais d’expédition et les défauts apparents doivent être précisés avant la conclusion du contrat. Seules les images pour lesquelles l’utilisateur dispose des droits nécessaires peuvent être publiées. La messagerie interne sert à échanger au sujet de l’offre concernée. Le harcèlement, la publicité de masse, l’hameçonnage, le contournement des blocages et la publication de contenus privés appartenant à des tiers sont interdits.

## Droits et cessation
Les annonces gratuites à prix fixe pour les particuliers sont proposées dans les limites publiées du service. Toute mise en avant payante exige une commande distincte. La plateforme ne garantit ni la vente ni un niveau de visibilité. Les utilisateurs peuvent gérer les offres enregistrées et leurs fonctions personnelles selon les possibilités offertes par la plateforme et demander la désactivation de leur compte. Les contrats en cours, les obligations légales de conservation et les éléments de preuve légitimes liés à un litige peuvent empêcher l’effacement immédiat et complet de certaines données. Les décisions doivent être expliquées de manière compréhensible et pouvoir être réexaminées.

[REQUIRED: vérifier l’âge minimal pour créer un compte, les pays autorisés, les limites effectives de la gratuité et la confirmation du contrat.]
MD,
        ],
        'dealer' => [
            'title' => 'Conditions complémentaires applicables aux vendeurs professionnels',
            'provisions' => <<<'MD'
## Identité professionnelle
Les vendeurs professionnels doivent déclarer leur statut réel et fournir l’ensemble des informations légalement requises sur l’entreprise, ses coordonnées, son immatriculation, sa fiscalité et la protection des consommateurs. L’exploitant de la plateforme vérifie, selon la procédure prévue, les informations sur les vendeurs que la loi lui impose de contrôler. Un profil public ou un logo téléversé ne remplace pas une autorisation administrative ou une validation spécialisée.

## Offres et obligations à l’égard des consommateurs
Les vendeurs professionnels sont responsables de l’exactitude de la description de l’article, du prix total, des frais de livraison, de la disponibilité, des droits légaux liés aux défauts du bien et des informations obligatoires sur la rétractation pour leur propre contrat de vente. La plateforme demeure responsable de ses propres obligations légales. Les offres transfrontalières supposent de clarifier, pour les pays effectivement visés, les modalités de livraison, le traitement fiscal, les informations destinées aux consommateurs et les types de produits autorisés.

## Gestion et publicité
Les importations en masse doivent respecter les formats et les règles de produits applicables. Les lignes contenant des erreurs ne doivent pas être présentées comme correctement importées. L’accès des membres du personnel nécessite des habilitations distinctes dès lors que cette fonctionnalité est effectivement proposée. Les comptes d’administration partagés sans contrôle ne sont pas prévus. Les services publicitaires sont soumis à une réservation distincte, à une vérification et aux conditions publicitaires. Aucun volume de ventes ni agrément légal du vendeur professionnel n’est garanti.

[REQUIRED: vérifier le contrôle d’identité, les coordonnées des vendeurs professionnels, les territoires de commercialisation autorisés, la communication contractuelle et, le cas échéant, les obligations P2B.]
MD,
        ],
        'auction' => [
            'title' => 'Conditions des enchères pour les biens courants',
            'provisions' => <<<'MD'
## Périmètre des offres et informations contraignantes
Les enchères sont réservées aux biens courants dont les transactions sont autorisées sur la plateforme. Le vendeur fixe le prix de départ, la durée, les conditions de remise du bien et, le cas échéant, l’option d’achat immédiat. Il est interdit pour un vendeur d’enchérir sur sa propre offre, de recourir à des enchérisseurs de complaisance ou de faire monter artificiellement le prix. L’identité associée au compte et la catégorie ne remplacent pas une autorisation légale relative au produit.

## Enchères et clôture
Le plafond d’enchère confidentiel n’est pas affiché publiquement. Les enchères automatiques n’augmentent le montant affiché que selon la règle publiée sur les paliers d’enchère. À plafonds égaux, la règle technique donne la priorité à l’enchère acceptée en premier. Une enchère valide dans les deux dernières minutes repousse la clôture à au moins deux minutes après son acceptation. L’heure d’acceptation par le serveur fait foi. Les enchères tardives, non autorisées ou placées par le vendeur sont rejetées.

## Contrat et incidents
[REQUIRED: déterminer, après examen de l’interface effective, le moment précis de la conclusion du contrat et les déclarations qui y concourent.] L’enchérisseur gagnant et le vendeur reçoivent, par les moyens de contact proposés, les informations nécessaires à l’exécution du contrat. En cas de données contradictoires, le passage en état de vérification ne permet pas de désigner arbitrairement un gagnant. Sont interdits les modifications substantielles après réception d’enchères, la clôture anticipée injustifiée et le contournement de contrats valides. Les incidents techniques, la contestation du contrat, le retrait justifié et les contrats dont l’échec est établi doivent être traités selon une procédure documentée. Les frais ne sont dus qu’à la survenance des événements convenus. Une créance impayée ne constitue pas un paiement confirmé.
MD,
        ],
        'fees' => [
            'title' => 'Barème des frais et des prix',
            'provisions' => <<<'MD'
## Règles de tarification
Le prix total applicable est celui affiché pour la réservation concernée avant la commande avec obligation de paiement et enregistré avec la version du barème. La configuration administrative en vigueur ne peut augmenter rétroactivement le prix de commandes antérieures. La taxe, le montant hors taxes et le montant toutes taxes comprises doivent être indiqués séparément selon le traitement fiscal effectivement vérifié. [REQUIRED: statut fiscal de l’exploitant, traitement de la TVA et, le cas échéant, mentions d’exonération.]

## Tarifs standard prévus
Les annonces de particuliers à prix fixe, les annonces de recherche et les alertes de recherche sont gratuites dans les limites fixées. Les tarifs prévus sont de 0,50 EUR pour la mise aux enchères, de 1,99 EUR par Bump, de 4,99 EUR pour trois Bumps, de 9,90 EUR pour une mise en tête de catégorie de sept jours, de 19,90 EUR pour une mise en tête de page d’accueil de sept jours et de 129 EUR pour une bannière de vendeur professionnel d’un mois. La présence d’un tarif dans cette liste n’active aucune fonctionnalité qui n’est pas encore proposée. Tout tarif différent fixé dans l’administration doit être clairement affiché avant la commande.

## Commission progressive sur les enchères
La commission sur le prix de vente s’élève à 3 % sur les premiers 100 EUR, à 2 % sur la tranche supérieure à 100 EUR et allant jusqu’à 500 EUR, à 1 % sur la tranche supérieure à 500 EUR et allant jusqu’à 2 000 EUR, puis à 0,5 % au-delà, dans la limite de 29,90 EUR. Les frais de mise aux enchères s’y ajoutent. La commission de vente suppose une conclusion valide et réussie selon les règles convenues. Les annulations, les transactions dont il est établi qu’elles n’ont pas été conclues et les remboursements donnent lieu à des corrections documentées. Une estimation des frais n’est pas une facture et la déclaration d’un client ne vaut pas encaissement.
MD,
        ],
        'premium' => [
            'title' => 'Conditions applicables aux Bumps et aux emplacements premium',
            'provisions' => <<<'MD'
## Étendue du service
Un Bump fait remonter une annonce existante de l’utilisateur dans le classement par ordre de récence. La date de création initiale, les enchères et la date de clôture des enchères restent inchangées. Un même article ne peut bénéficier que d’un Bump par période de 72 heures. Le crédit est mis à disposition après le contrôle de paiement prévu, puis l’utilisateur l’active par une action volontaire.

## Nombre limité d’emplacements et durées
Les emplacements en tête de catégorie et en tête de page d’accueil sont réservables dans les limites effectivement ouvertes. Le nombre de places disponibles, le pays ou la catégorie concernée, le prix total, le délai de réservation et la durée sont indiqués avant la commande. Une réservation impayée qui a expiré ne garantit pas un emplacement permanent. Si le paiement intervient alors qu’il n’y a plus de place disponible, l’exploitant doit éviter toute surréservation ou activation fictive et proposer une solution de remplacement documentée ou un examen du remboursement.

## Signalement publicitaire, résultats et droits
Les emplacements payants sont signalés comme publicitaires ou sponsorisés. En l’absence d’accord exprès distinct, aucun nombre déterminé de consultations, de contacts ou de ventes n’est garanti. Les droits légaux des consommateurs restent applicables. Le démarrage immédiat du service exige la déclaration expresse prévue par la loi. La commande seule ne vaut pas renonciation générale au droit de rétractation. En cas de forfaits partiellement utilisés ou de pannes techniques, une correction appropriée et un examen du remboursement au regard de la loi doivent être prévus.

[REQUIRED: vérifier le nombre effectif d’emplacements, la répartition par pays, les règles de démarrage, la compensation des interruptions et la procédure de rétractation.]
MD,
        ],
        'advertising' => [
            'title' => 'Conditions publicitaires applicables aux vendeurs professionnels',
            'provisions' => <<<'MD'
## Campagne et vérification
La publicité ne peut être réservée que pour des services ou des biens courants licites et adaptés à la plateforme. L’identité de l’annonceur, l’adresse de destination, le visuel et les mentions obligatoires doivent être exacts. Les présentations trompeuses d’autorisations, les produits illicites, les logiciels malveillants et les redirections dissimulées sont interdits. La validation d’un visuel publicitaire ne vaut pas autorisation administrative ou juridique du modèle économique.

## Diffusion et disponibilité
Le pays, la catégorie, la durée, le nombre d’emplacements alloués et le prix total sont fixés avant la réservation. Une diffusion ciblée par pays ne garantit pas que tous les consommateurs qui peuvent y être atteints puissent être livrés sans obligations supplémentaires. Une campagne ne peut être présentée comme en cours de diffusion qu’après vérification, paiement et activation pour la période prévue. Un emplacement en quantité limitée ne peut être vendu deux fois.

## Statistiques et cessation
Les nombres de clics et d’impressions sont des mesures internes établies selon la méthode de comptage publiée. Ils ne garantissent pas un nombre de personnes distinctes et ne constituent pas une prévision de ventes. Les clics de l’annonceur visant à fausser les mesures, les abus automatisés et le profilage illicite sont interdits. Une campagne peut être suspendue de façon proportionnée en cas de problème juridique ou de sécurité dûment motivé. Il faut documenter le motif, la possibilité de recours et le traitement de la durée payée qui n’a pas été utilisée.

[REQUIRED: compléter l’identité du prestataire, la durée mensuelle du contrat, les règles de renouvellement et de résiliation, la définition des statistiques et les règles applicables aux interruptions et aux remboursements.]
MD,
        ],
        'verification' => [
            'title' => 'Conditions de vérification et de sécurité',
            'provisions' => <<<'MD'
## Vérifications distinctes
L’identité et l’âge minimal font l’objet de vérifications distinctes. Aucune de ces vérifications n’atteste la propriété d’un bien, le droit légal de l’acquérir, la solvabilité ou la probité de l’utilisateur. Ces vérifications ne débloquent aucune fonctionnalité de transaction bloquée pour des biens réglementés.

## Documents et finalité
Seuls les champs des documents nécessaires à la finalité de vérification annoncée peuvent être traités. Les données non nécessaires doivent pouvoir être masquées. Les documents ne doivent pas figurer parmi les images publiques des produits ni dans la messagerie ordinaire. Avant leur transmission, l’entité responsable, la finalité, la base juridique, la durée effective de conservation et le point de contact pour les demandes de renseignements doivent être présentés clairement. La collecte reste désactivée tant que ces conditions et le dispositif technique de stockage n’ont pas été validés.

## Accès, décision et effacement
Les images des documents sont contrôlées, réencodées et stockées sous forme chiffrée en dehors du répertoire racine du serveur web. Seuls les agents habilités à examiner le dossier, avec authentification multifacteur, obtiennent un accès temporaire. Les consultations et les décisions sont journalisées. Les validations, demandes de pièces complémentaires et refus doivent pouvoir être expliqués. Les fichiers sont supprimés de manière sécurisée à l’expiration de la durée de conservation nécessaire des documents, déterminée après examen spécialisé. Seul un enregistrement minimal de la décision peut être conservé plus longtemps, sous réserve d’une base juridique propre.

[REQUIRED: déterminer, après examen spécialisé, l’entité responsable de la vérification, la base juridique pour chaque justificatif, les types de documents, les délais précis et le réexamen.]
MD,
        ],
        'privacy' => [
            'title' => 'Politique de confidentialité',
            'provisions' => <<<'MD'
## Responsable du traitement et coordonnées
[REQUIRED: identité complète du responsable du traitement, adresse, contact pour la protection des données et, le cas échéant, délégué à la protection des données requis.] La présente déclaration doit correspondre aux systèmes effectivement utilisés et aux pays ouverts. Ce projet ne présume pas qu’un examen de la protection des données a déjà été mené à terme.

## Finalités et bases juridiques
Les données du compte, les offres et les communications contractuelles nécessaires sont traitées pour fournir les services et exécuter le contrat d’utilisation dans la mesure où l’article 6, paragraphe 1, point b), du RGPD le permet. Les données financières ou justificatives légalement exigées reposent, le cas échéant, sur le point c). La prévention proportionnée des abus et la tenue de journaux de sécurité exigent une mise en balance concrète et documentée des intérêts au titre du point f). La simple utilisation du service ne saurait remplacer de façon générale un consentement effectivement libre au titre du point a). La nécessité précise du traitement des pièces d’identité et des informations propres à certains dossiers doit être déterminée séparément.

## Données, destinataires et sécurité
Selon la fonctionnalité, les traitements portent sur les données du compte et les coordonnées, les offres publiques, les messages privés autorisés, les enchères, l’état des commandes et des paiements, les préférences et les données minimales nécessaires à la sécurité et à la modération. L’accès aux messages privés est restreint et, si cette mesure est mise en œuvre, ils sont chiffrés côté serveur. Ce chiffrement ne s’effectue pas de bout en bout. Les prestataires de paiement peuvent agir comme responsables de traitement distincts pour leurs propres services. L’hébergement, le transport local des courriels et les autres destinataires effectifs doivent être indiqués précisément. [REQUIRED: destinataires, sous-traitance, lieux de stockage, transferts vers des pays tiers et garanties.]

## Durées et droits
[REQUIRED: durées justifiables pour chaque catégorie de données, notamment les comptes, les contrats, les messages, les journaux de sécurité et les documents.] Les utilisateurs peuvent exercer leurs droits d’accès, de rectification, d’effacement dans les cas prévus par la loi, de limitation du traitement, de portabilité et, le cas échéant, d’opposition ou de retrait du consentement. Une demande n’écarte pas les obligations impératives de conservation. Il reste possible d’introduire une réclamation auprès d’une autorité compétente en matière de protection des données. [REQUIRED: autorité compétente et coordonnées claires pour l’exercice des droits.]
MD,
        ],
        'imprint' => [
            'title' => 'Mentions légales et informations sur l’exploitant',
            'provisions' => <<<'MD'
## Fournisseur de services
Dénomination légale complète : [REQUIRED: nom du fournisseur et forme juridique].
Adresse à laquelle les actes peuvent être signifiés : [REQUIRED: rue, numéro, code postal, localité, pays].
Personne habilitée à représenter le fournisseur : [REQUIRED: identité du représentant effectif, si nécessaire].
Coordonnées : [REQUIRED: adresse électronique et autre moyen effectivement approprié de communication directe].

## Registre et informations fiscales
Registre et numéro d’immatriculation : [REQUIRED: informations effectives sur l’immatriculation, lorsqu’elles existent et sont requises].
Numéro d’identification à la TVA ou identifiant économique requis : [REQUIRED: informations exactes ou justification spécialisée de leur caractère inapplicable].
Mentions obligatoires relatives aux agréments, à la profession ou à la surveillance : [REQUIRED: seules les informations effectivement nécessaires et exactes]. Aucune compétence d’une autorité ni autorisation ne peut être inventée.

## Responsabilité éditoriale et règlement des litiges
[REQUIRED: le cas échéant, personne responsable du contenu au regard du droit applicable].
Les informations sur le règlement extrajudiciaire des litiges de consommation doivent refléter fidèlement la disposition effective à participer ou l’obligation légale de le faire. Les liens obsolètes vers des plateformes ou les affirmations générales en matière de règlement des litiges ne peuvent être repris sans vérification. Le contact pour la protection des données et le moyen de signalement doivent être accessibles. Cette structure ne peut être publiée comme mentions légales avant d’avoir été complétée et vérifiée par un spécialiste.
MD,
        ],
        'cookies' => [
            'title' => 'Informations sur les cookies et le stockage',
            'provisions' => <<<'MD'
## Fonctionnalités techniquement nécessaires
La connexion utilise un identifiant de session pour associer les accès au compte autorisés aux formulaires correspondants. Des mesures de sécurité telles que HttpOnly et SameSite, ainsi que Secure lorsque HTTPS est utilisé, sont mises en œuvre. Un jeton CSRF protège les formulaires qui modifient l’état de l’application. Ces fonctionnalités ne valent pas consentement au suivi publicitaire.

## Préférences d’affichage
Le thème de couleurs, la mise en pause des animations et, le cas échéant, la langue ou le pays du marché peuvent être enregistrés localement ou dans la session afin de rétablir l’affichage choisi. [REQUIRED: vérifier les noms, les types de stockage, les durées et les finalités effectifs pour chaque élément stocké]. L’interface doit respecter tout changement de sélection. Un consentement exigé par le droit de la protection des données ne peut être présélectionné.

## Statistiques et prestataires tiers
Ce projet n’active à lui seul aucun réseau publicitaire ni service externe de mesure d’audience. Les mesures locales effectivement mises en œuvre, les compteurs de prévention des abus et les éventuelles redirections vers PayPal doivent être décrits de manière transparente. Avant d’activer tout accès à un espace de stockage qui n’est pas nécessaire, il faut vérifier si le droit applicable exige un consentement. Tout outil ajouté par la suite exige une nouvelle évaluation technique et juridique. L’autorisation existante relative à la session ne s’étend pas automatiquement à cet outil.

[REQUIRED: inventaire complet des cookies effectivement déposés et des éléments effectivement enregistrés dans le stockage local, coordonnées et gestion du consentement.]
MD,
        ],
        'withdrawal' => [
            'title' => 'Informations sur le droit de rétractation applicable aux services concernés de la plateforme',
            'provisions' => <<<'MD'
## Champ d’application
Cette version doit être adaptée aux services payants effectivement proposés et aux consommateurs bénéficiant d’une protection légale. [REQUIRED: faire vérifier les pays ouverts, le statut de consommateur et les types de contrats concernés.] Les droits impératifs différents que le droit applicable accorde aux consommateurs ne peuvent être écartés.

## Droit de rétractation
Lorsqu’un droit légal de rétractation existe, les consommateurs peuvent se rétracter du contrat dans un délai de quatorze jours, sans avoir à motiver leur décision. Pour un contrat de service, le délai court en principe à compter de la conclusion du contrat, sous réserve du respect des obligations légales d’information. L’exercice de ce droit exige une déclaration sans équivoque adressée à [REQUIRED: nom, adresse et adresse électronique de l’exploitant]. L’utilisation du formulaire type est facultative. Le délai est respecté si la déclaration est envoyée avant son expiration, dans les conditions prévues par la loi.

## Effets de la rétractation et commencement anticipé
Les paiements reçus doivent être remboursés dans les délais et aux conditions prévus par la loi, en principe par le même moyen de paiement, sauf accord contraire valablement conclu. Si le consommateur a expressément demandé que le service commence pendant le délai de rétractation, une indemnité proportionnée prévue par la loi ne peut être envisagée que si les conditions d’information et de déclaration sont satisfaites. La perte totale du droit de rétractation pour un service suppose que les conditions légales applicables soient remplies. L’activation d’un Bump ou le paiement seul ne vaut pas renonciation générale aux droits relatifs au crédit inutilisé ou aux autres prestations du forfait.

[REQUIRED: vérifier le parcours effectif de commande, de confirmation, de commencement du service et de remboursement. Ne pas assimiler les services aux contenus numériques sans vérification.]
MD,
        ],
        'withdrawal-form' => [
            'title' => 'Formulaire type de rétractation',
            'provisions' => <<<'MD'
## Utilisation
Ce formulaire peut servir à se rétracter d’un contrat de consommation portant sur un service concerné de la plateforme. Son utilisation n’est pas obligatoire pour présenter une déclaration sans équivoque valable. Il ne remplace pas les informations complètes sur le droit de rétractation. Rayer les mentions inutiles.

## Déclaration
À l’attention de [REQUIRED: nom légal de l’exploitant, adresse complète et adresse électronique] :

Je vous notifie/Nous vous notifions par la présente ma/notre rétractation du contrat que j’ai/nous avons conclu pour le service suivant de la plateforme :

Service et, le cas échéant, référence de la commande : ______________________________
Date de commande : ______________________________
Nom du ou des consommateurs : ______________________________
Adresse du ou des consommateurs : ______________________________
Date : ______________________________
Signature du ou des consommateurs, uniquement en cas de déclaration sur papier : ______________________________

## Traitement
Seules les informations nécessaires à l’identification et au traitement de la demande doivent être fournies. La transmission de copies de pièces d’identité, d’identifiants de connexion ou de données de paiement confidentielles complètes n’est en principe pas nécessaire. L’exploitant doit fournir un moyen de réception effectivement accessible et une procédure de remboursement documentée. La réception du formulaire ne dépend pas du paiement de frais de traitement.

[REQUIRED: mettre en concordance les coordonnées et les contrats effectivement concernés avec les informations sur le droit de rétractation avant publication.]
MD,
        ],
        'moderation' => [
            'title' => 'Règles de modération',
            'provisions' => <<<'MD'
## Principes
La modération repose sur des règles de la plateforme compréhensibles et sur le droit applicable. Les contenus illicites, les fraudes, les fichiers téléversés dangereux, le harcèlement, les avis manipulés et les biens réglementés dont la vente n’a pas été autorisée sur la plateforme sont interdits. La plateforme ne peut transférer intégralement ses propres obligations légales aux utilisateurs.

## Procédure
Un numéro de dossier est attribué à chaque signalement. Les signalements sont examinés selon l’urgence et la compétence des responsables. L’accès doit être limité aux informations nécessaires. Un rôle général d’administration ne suffit pas à donner accès à l’intégralité des conversations privées. Les informations complémentaires fournies volontairement doivent être traitées aux seules fins prévues. Les décisions doivent être motivées conformément au droit applicable. Les utilisateurs concernés doivent être informés des voies de recours disponibles.

## Mesures et limites
Les mesures possibles comprennent un rappel, un avertissement, la mise en attente temporaire ou le masquage de contenus précis et, lorsque le responsable est habilité à le faire, une restriction de compte. La portée, la durée, la gravité et la répétition doivent être prises en compte de manière proportionnée. En présence d’incohérences, une vente aux enchères pour laquelle des offres ont été reçues passe en état de vérification. Aucune enchère ni aucun gagnant ne peut être inventé ou supprimé de façon dissimulée. Une décision rendue dans le dossier ne remplace pas une décision judiciaire sur des demandes de droit civil.

[REQUIRED: faire vérifier les compétences effectives, les exceptions légales, le contenu de la motivation, l’applicabilité du DSA et les délais.]
MD,
        ],
        'complaints' => [
            'title' => 'Procédure de signalement et de réclamation',
            'provisions' => <<<'MD'
## Signalement
Les utilisateurs peuvent signaler, par le moyen prévu, des offres, des utilisateurs, des messages auxquels ils ont un accès autorisé, des avis et des publicités publiées. L’objet du signalement doit être suffisamment identifiable et le motif compréhensible. Les informations complémentaires volontaires doivent se limiter à ce qui est nécessaire. Une demande légitime ne peut être subordonnée à la divulgation de données d’identité ou de paiement inutiles.

## Examen et décision
La plateforme attribue un numéro de dossier et documente l’examen, la personne ou le service chargé du traitement, la décision et sa motivation. Un signalement ne déclenche pas automatiquement une sanction. Lorsque la loi l’exige, la réception du signalement et la décision sont communiquées aux parties habilitées. L’identité de l’auteur du signalement et les justificatifs confidentiels ne peuvent être divulgués systématiquement à l’autre partie.

## Réclamation
Les parties concernées habilitées peuvent demander, par l’accès prévu au dossier, le réexamen de la décision communiquée en motivant leur demande. La réclamation doit être effectivement traitée. Son enregistrement ne lève pas automatiquement la mesure. Le réexamen des faits ou la confirmation de la décision est documenté. Les procédures externes de réclamation, de règlement des litiges et de recours prévues par la loi demeurent accessibles. Une limitation technique interne des saisies identiques répétées ne peut restreindre illicitement des droits impératifs.

[REQUIRED: préciser le contact pour le signalement des contenus illicites, les délais effectifs, l’applicabilité du DSA et les voies de recours extrajudiciaires.]
MD,
        ],
        'disputes' => [
            'title' => 'Conditions applicables en cas de litige',
            'provisions' => <<<'MD'
## Parties au contrat
Les litiges relatifs à l’état du bien, au paiement, à la livraison ou à l’exécution de la vente concernent en principe l’acheteur et le vendeur en tant que parties distinctes au contrat. La plateforme technique ne devient pas automatiquement vendeur, prestataire d’expédition, assureur ou tribunal arbitral. Elle s’acquitte de ses propres obligations légales et de celles qu’elle a expressément assumées.

## Assistance
La plateforme peut mettre à disposition les moyens de communication autorisés existants et une procédure interne de signalement. Dans ce cadre, seules les données nécessaires sont rendues accessibles. Les utilisateurs ne peuvent fournir de faux justificatifs, utiliser le compte d’un tiers ou engager des contestations de paiement infondées. Une vérification interne en cours ne confirme pas automatiquement la demande de l’une des parties.

## Preuves et voies de recours
Les événements relatifs aux enchères, aux paiements et aux dossiers ne peuvent être manipulés rétroactivement de façon dissimulée. Les justificatifs nécessaires sont conservés conformément à une base juridique et à une règle de conservation documentées. Une confirmation manuelle de paiement atteste le contrôle effectué par l’exploitant et ne constitue pas une confirmation judiciaire ou cryptographique indépendante. Les droits prévus par la loi, les procédures judiciaires et les mécanismes extrajudiciaires de règlement des litiges effectivement proposés restent accessibles. Aucun service de séquestre ni aucune garantie couvrant intégralement le prix d’achat ne sont promis.

[REQUIRED: vérifier l’assistance volontaire précise, la gratuité ou le prix, l’entité compétente et les informations légalement requises sur le règlement extrajudiciaire des litiges.]
MD,
        ],
        'payments' => [
            'title' => 'Conditions de paiement des services de la plateforme',
            'provisions' => <<<'MD'
## Distinction avec la vente de biens
Les présentes conditions concernent les sommes dues à l’exploitant pour ses propres services. Elles ne régissent pas un encaissement général des prix de vente entre utilisateurs. Les moyens de paiement disponibles pour la commande concernée sont indiqués avant la conclusion du contrat. La seule présence d’une option désactivée à l’écran ne rend pas utilisable le virement bancaire, le Bitcoin ou le séquestre.

## Contrôle manuel et automatique
Une instruction de paiement manuel par PayPal indique un montant fixe en EUR, une description exacte du service, le destinataire et une référence unique. L’exploitant vérifie que le paiement a bien été reçu et finalisé et lui associe un identifiant de transaction unique. Une déclaration de l’utilisateur, une capture d’écran ou un paramètre du navigateur ne prouve pas le paiement. La procédure manuelle ne dispense pas de respecter les conditions relatives au compte PayPal et ne justifie pas un paiement Friends & Family pour des services commerciaux. Une intégration automatique exige la configuration du prestataire effectivement autorisée et des événements vérifiés côté serveur.

## Écarts et exécution
Si le montant, la référence ou le destinataire diffère de ce qui est prévu, ou si l’opération de paiement est incertaine, la commande ne peut être validée comme intégralement payée. Une transaction ne peut être comptabilisée plusieurs fois. Le moment de l’activation du service est déterminé par la commande confirmée. Une réservation d’emplacement expirée ne peut donner lieu à une surréservation après coup. Les erreurs, annulations et remboursements doivent être documentés au moyen d’écritures de correction. Les factures antérieures ne peuvent être modifiées de façon dissimulée.

[REQUIRED: vérifier l’adéquation du compte du destinataire, les délais de paiement, les retards de paiement, les conditions effectives du prestataire et la confirmation du contrat.]
MD,
        ],
        'refunds' => [
            'title' => 'Conditions d’annulation et de remboursement',
            'provisions' => <<<'MD'
## Motifs d’examen
Une demande de remboursement peut notamment concerner l’exercice du droit de rétractation, des services réservés qui n’ont pas été fournis, des paiements en double, des corrections contractuelles dont le bien-fondé est établi ou des pannes techniques. Cette description ne peut restreindre les droits prévus par la loi. Le droit au remboursement est examiné de manière vérifiable au regard de la commande et des services effectivement fournis.

## Documentation et rapprochement
La demande, la référence, le motif, la décision, le montant et le moyen de paiement effectivement utilisé doivent être documentés. Le statut « demandé » n’atteste pas qu’un remboursement a été exécuté. Un remboursement automatique ne peut être considéré comme achevé qu’après vérification de la confirmation du prestataire de paiement. Un remboursement manuel exige un rapprochement vérifiable avec la transaction effectivement réalisée. Les documents déjà émis ne sont pas remplacés de façon dissimulée. Des justificatifs d’annulation ou de correction sont établis si nécessaire.

## Crédits et prestations partielles
La part consommée et la part inutilisée des forfaits de Bumps et des services à durée déterminée doivent être examinées séparément. L’exécution immédiate d’un seul Bump ne justifie pas l’exclusion générale de tous les droits relatifs au solde du forfait. Si l’emplacement premium n’est plus disponible à la réception d’un paiement tardif, une solution concrète doit être examinée. Un emplacement qui paraît actif ne vaut pas exécution du service. Les délais de remboursement et les moyens de paiement admissibles dépendent de la loi et des conditions valablement convenues.

[REQUIRED: faire vérifier les procédures effectives de traitement, les délais légaux, les justificatifs de correction et les procédures du prestataire de paiement.]
MD,
        ],
        'allowed-products' => [
            'title' => 'Règles relatives aux produits autorisés et interdits',
            'provisions' => <<<'MD'
## Marché actif
La place de marché active est destinée aux biens courants licites et non réglementés, par exemple les articles de plein air, de camping, d’habillement, de sport et d’optique, les articles ménagers, les outils et les supports médiatiques. La catégorie choisie ne dispense ni d’un examen du produit ni du respect des exigences du pays de livraison ou du pays du destinataire. Le simple changement de nom d’anciennes offres spécialisées ne transforme pas les produits concernés en biens courants.

## Produits bloqués et produits à examiner
Les produits illicites, les contrefaçons, les biens volés, les offres frauduleuses et les catégories non autorisées de produits réglementés, notamment les armes, les munitions ou d’autres produits soumis à des contraintes juridiques, ne peuvent être commercialisés dans les catégories générales. Un libellé saisi librement ou un statut de vérification ne confère aucune autorisation. Si la classification est incertaine, un examen spécialisé est nécessaire. Les transactions concernées restent bloquées jusque-là.

## Obligations et application des règles
Les vendeurs doivent avoir le droit de disposer du bien, fournir des informations exactes et respecter les exigences applicables au produit, à l’expédition et au pays de destination. L’exploitant doit s’acquitter de ses propres obligations légales et de modération. Les filtres textuels sont un outil complémentaire et ne suffisent pas à établir une classification juridique complète. Les tentatives de contournement peuvent entraîner des restrictions proportionnées selon la procédure de modération, avec indication des motifs.

[REQUIRED: établir, après examen spécialisé, la liste contraignante des catégories de produits, les pays ouverts, le responsable de la vérification et les restrictions légales par type de produit.]
MD,
        ],
        'account-suspension' => [
            'title' => 'Règles de restriction et de suspension des comptes',
            'provisions' => <<<'MD'
## Motifs et proportionnalité
En cas de problème juridique, de fraude ou de sécurité dûment motivé, un compte peut faire l’objet d’une restriction temporaire dans la mesure où celle-ci est légalement admissible et nécessaire pour prévenir le danger. La mesure doit tenir compte de la nature, de la gravité, de la durée, de la répétition et des effets de la situation. Les suspensions non motivées ou purement arbitraires ne sont pas prévues.

## Effets et opérations en cours
La désactivation du compte révoque les sessions actives et empêche de nouvelles actions non autorisées. Les enchères, les contrats, les paiements et les réclamations existants subsistent. Une vente aux enchères en cours pour laquelle des enchères ont été reçues ne peut être manipulée par la suppression dissimulée d’un participant. La mise en état de vérification et le traitement séparé du dossier préservent les preuves nécessaires.

## Motivation et réexamen
Lorsque la loi l’exige, le motif, la portée, la durée et les possibilités de réexamen sont communiqués. Les exceptions légales, notamment pour protéger une enquête ou des tiers, doivent être examinées concrètement. Les utilisateurs doivent pouvoir présenter une réclamation justifiée. Les droits qu’ils peuvent exercer par des voies externes subsistent. La suppression volontaire d’un compte doit être distinguée d’une sanction et exige une décision de conservation propre au regard du droit de la protection des données.

[REQUIRED: vérifier les délais, le réexamen, la communication après désactivation, l’applicabilité du DSA et des règles P2B ainsi que les dispositions contractuelles de résiliation.]
MD,
        ],
    ],
];
