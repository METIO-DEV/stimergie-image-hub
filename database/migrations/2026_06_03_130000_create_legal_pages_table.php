<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_type')->unique();
            $table->string('title');
            $table->longText('content');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('legal_pages')->insert([
            [
                'page_type' => 'privacy_policy',
                'title' => 'Politique de confidentialité',
                'content' => <<<'HTML'
<p style="text-align: left;"><strong>Identit&eacute; du responsable de traitement</strong></p>
<p style="text-align: left;"><strong>Agence impronon&ccedil;able</strong></p>
<p style="text-align: left;"><strong>75, rue Parmentier- 93100 Montreuil</strong></p>
<p style="text-align: left;"><strong><a href="mailto:contact@imprononcable.com">contact@imprononcable.com</a></strong></p>
<p style="text-align: left;"><strong>Collecte des donn&eacute;es personnelles</strong></p>
<p style="text-align: left;">Nous collectons les donn&eacute;es personnelles suivantes :</p>
<ul style="text-align: left;">
<li>Donn&eacute;es de navigation (adresse IP, pages visit&eacute;es, dur&eacute;e de visite)</li>
<li>Donn&eacute;es fournies volontairement (formulaires de contact, newsletter)</li>
<li>Cookies techniques et de mesure d'audience</li>
</ul>
<p style="text-align: left;"><strong>Finalit&eacute;s du traitement</strong></p>
<p style="text-align: left;">Vos donn&eacute;es sont trait&eacute;es pour :</p>
<ul style="text-align: left;">
<li>Le fonctionnement du site web</li>
<li>La r&eacute;ponse aux demandes de contact</li>
<li>L'am&eacute;lioration de nos services</li>
<li>Le respect de nos obligations l&eacute;gales</li>
</ul>
<p style="text-align: left;"><strong>Base l&eacute;gale</strong></p>
<p style="text-align: left;">Le traitement de vos donn&eacute;es repose sur :</p>
<ul style="text-align: left;">
<li>Votre consentement (cookies non essentiels)</li>
<li>L'int&eacute;r&ecirc;t l&eacute;gitime (am&eacute;lioration du site)</li>
<li>L'ex&eacute;cution d'un contrat (services demand&eacute;s)</li>
</ul>
<p style="text-align: left;"><strong>Dur&eacute;e de conservation</strong></p>
<ul style="text-align: left;">
<li>Donn&eacute;es de navigation : 13 mois maximum</li>
<li>Donn&eacute;es de contact : 3 ans apr&egrave;s le dernier contact</li>
<li>Cookies : selon les dur&eacute;es sp&eacute;cifiques mentionn&eacute;es dans la banni&egrave;re</li>
</ul>
<p style="text-align: left;"><strong>H&eacute;bergement</strong></p>
<p style="text-align: left;">Ce site est h&eacute;berg&eacute; par <strong>Hostinger</strong> en France. Toutes les donn&eacute;es sont stock&eacute;es sur le territoire fran&ccedil;ais et soumises &agrave; la l&eacute;gislation fran&ccedil;aise et europ&eacute;enne.</p>
<p style="text-align: left;"><strong>Vos droits</strong></p>
<p style="text-align: left;">Conform&eacute;ment au RGPD, vous disposez des droits suivants :</p>
<ul style="text-align: left;">
<li>Droit d'acc&egrave;s &agrave; vos donn&eacute;es</li>
<li>Droit de rectification</li>
<li>Droit &agrave; l'effacement ("droit &agrave; l'oubli")</li>
<li>Droit &agrave; la portabilit&eacute;</li>
<li>Droit d'opposition</li>
<li>Droit &agrave; la limitation du traitement</li>
</ul>
<p style="text-align: left;">Pour exercer ces droits, contactez-nous &agrave; : <strong><a href="mailto:contact@stimergie.fr">contact@stimergie.fr</a></strong></p>
<p style="text-align: left;"><strong>Cookies</strong></p>
<p style="text-align: left;">Notre site utilise des cookies. Vous pouvez g&eacute;rer vos pr&eacute;f&eacute;rences via la banni&egrave;re de cookies pr&eacute;sente sur le site.</p>
HTML,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'page_type' => 'terms_of_service',
                'title' => "Conditions d'utilisation",
                'content' => <<<'HTML'
<p style="text-align: left;"><strong>Stimergie &ndash; Description du service pour les Conditions G&eacute;n&eacute;rales d&rsquo;Utilisation</strong></p>
<ol style="text-align: left;">
<li><strong>Objet du service</strong></li>
</ol>
<p style="text-align: left;">Stimergie est une plateforme web de gestion et de diffusion de contenus num&eacute;riques (images, documents associ&eacute;s) con&ccedil;ue pour aider les entreprises &agrave; centraliser, organiser, partager et valoriser leur patrimoine visuel. Ce service est mis &agrave; disposition par l&rsquo;agence impronon&ccedil;able, dans le cadre d&rsquo;un abonnement ou d&rsquo;une prestation sp&eacute;cifique.</p>
<ol style="text-align: left;">
<li><strong>Fonctionnalit&eacute;s propos&eacute;es</strong></li>
</ol>
<p style="text-align: left;">Stimergie permet notamment :</p>
<ul style="text-align: left;">
<li>la centralisation de contenus visuels au sein d&rsquo;un espace num&eacute;rique unique,</li>
<li>l&rsquo;organisation de ces contenus par collections, projets, ou campagnes,</li>
<li>l&rsquo;indexation des fichiers via des m&eacute;tadonn&eacute;es (tags, dates, auteurs, etc.),</li>
<li>la recherche et le filtrage par mots-cl&eacute;s ou crit&egrave;res,</li>
<li>le t&eacute;l&eacute;chargement de fichiers en diff&eacute;rentes r&eacute;solutions,</li>
<li>le partage de contenus via des liens temporaires ou des acc&egrave;s restreints,</li>
<li>la gestion des droits d&rsquo;usage,</li>
<li>un suivi des consultations et des t&eacute;l&eacute;chargements.</li>
</ul>
<ol style="text-align: left;">
<li><strong>Acc&egrave;s et s&eacute;curit&eacute;</strong></li>
</ol>
<p style="text-align: left;">L&rsquo;acc&egrave;s &agrave; Stimergie est strictement r&eacute;serv&eacute; aux utilisateurs autoris&eacute;s par le client. Chaque utilisateur dispose d&rsquo;un identifiant personnel. Le client est responsable de la gestion des droits d&rsquo;acc&egrave;s au sein de son organisation.</p>
<p style="text-align: left;">Les donn&eacute;es sont h&eacute;berg&eacute;es sur des serveurs s&eacute;curis&eacute;s localis&eacute;s en Europe, et b&eacute;n&eacute;ficient d&rsquo;un chiffrement des communications (HTTPS/TLS).</p>
<ol style="text-align: left;">
<li><strong>Droits d&rsquo;utilisation des contenus</strong></li>
</ol>
<p style="text-align: left;">Les contenus h&eacute;berg&eacute;s sur Stimergie restent la propri&eacute;t&eacute; intellectuelle de leurs auteurs, selon les termes des contrats de cr&eacute;ation ou d&rsquo;acquisition des visuels. Stimergie agit comme h&eacute;bergeur et outil de gestion, sans c&eacute;der ni acqu&eacute;rir de droits sur les m&eacute;dias d&eacute;pos&eacute;s.</p>
<p style="text-align: left;">Toutefois :</p>
<ul style="text-align: left;">
<li>Le client s&rsquo;engage &agrave; n&rsquo;utiliser sur Stimergie que des contenus dont il d&eacute;tient les droits d&rsquo;exploitation.</li>
<li>Stimergie ne pourra &ecirc;tre tenu responsable en cas de litige li&eacute; &agrave; l&rsquo;utilisation non autoris&eacute;e d&rsquo;un fichier par un utilisateur de la plateforme.</li>
</ul>
<ol style="text-align: left;">
<li><strong>Licence d&rsquo;acc&egrave;s au service</strong></li>
</ol>
<p style="text-align: left;">L&rsquo;acc&egrave;s &agrave; Stimergie est conc&eacute;d&eacute; au client sous forme de licence non exclusive, non transf&eacute;rable, pour la dur&eacute;e de l&rsquo;abonnement ou de la prestation. Cette licence inclut l&rsquo;acc&egrave;s &agrave; toutes les fonctionnalit&eacute;s pr&eacute;vues dans la formule souscrite, dans les limites d&rsquo;usage pr&eacute;cis&eacute;es (volum&eacute;trie, utilisateurs, bande passante, etc.).</p>
<ol style="text-align: left;">
<li><strong>Responsabilit&eacute;s</strong></li>
</ol>
<p style="text-align: left;">Stimergie garantit la continuit&eacute; du service dans des conditions normales d&rsquo;exploitation. Toutefois, la responsabilit&eacute; du prestataire ne saurait &ecirc;tre engag&eacute;e en cas :</p>
<ul style="text-align: left;">
<li>d&rsquo;interruption pour maintenance ou mise &agrave; jour,</li>
<li>de mauvaise utilisation de la plateforme,</li>
<li>de perte de donn&eacute;es li&eacute;e &agrave; une n&eacute;gligence du client (ex. : suppression accidentelle),</li>
<li>ou de force majeure.</li>
</ul>
<ol style="text-align: left;">
<li><strong>Mentions obligatoires et cr&eacute;dits</strong></li>
</ol>
<p style="text-align: left;">Lors de l&rsquo;utilisation publique des contenus issus de Stimergie, le client s&rsquo;engage &agrave; respecter les obligations de cr&eacute;dit associ&eacute;es aux visuels, lorsqu&rsquo;elles existent. Exemple :</p>
<p style="text-align: left;"><strong>&copy; Nom du photographe / impronon&ccedil;able</strong></p>
<ol style="text-align: left;">
<li><strong>Restrictions d&rsquo;usage commercial et publicitaire</strong></li>
</ol>
<p style="text-align: left;"><strong>8.1. &Eacute;tendue des droits de reproduction</strong></p>
<p style="text-align: left;">Sauf mention explicite contraire dans le devis, la facture ou les documents contractuels, les droits de reproduction accord&eacute;s sur les contenus h&eacute;berg&eacute;s sur Stimergie couvrent exclusivement une utilisation dans les contextes suivants :</p>
<ul style="text-align: left;">
<li>Communication institutionnelle (site internet, r&eacute;seaux sociaux organiques, newsletters, dossiers de presse, etc.)</li>
<li>Supports internes (pr&eacute;sentations, rapports, formations)</li>
<li>&Eacute;ditions &agrave; faible tirage sans vis&eacute;e commerciale directe (brochures, PLV informatives, menus, etc.)</li>
</ul>
<p style="text-align: left;"><strong>8.2. Exclusion des usages publicitaires</strong></p>
<p style="text-align: left;"><strong>Toute diffusion &agrave; vis&eacute;e publicitaire impliquant un achat d&rsquo;espace m&eacute;dia</strong> (print, digital, affichage, t&eacute;l&eacute;vision, presse, sponsoring, etc.) <strong>n&rsquo;est pas incluse par d&eacute;faut dans les droits de reproduction conc&eacute;d&eacute;s.</strong></p>
<p style="text-align: left;">Cela inclut notamment :</p>
<ul style="text-align: left;">
<li>Campagnes publicitaires payantes sur les r&eacute;seaux sociaux (Facebook Ads, Instagram Ads, TikTok, YouTube, etc.)</li>
<li>Annonces presse ou magazines</li>
<li>Affichage (abribus, m&eacute;tro, 4x3, etc.)</li>
<li>Spots TV ou pre-roll vid&eacute;o</li>
<li>Sponsoring de contenu, retargeting ou programmatique</li>
</ul>
<p style="text-align: left;"><strong>8.3. Cas d&rsquo;usage n&eacute;cessitant une autorisation compl&eacute;mentaire</strong></p>
<p style="text-align: left;">Pour ces usages, une demande &eacute;crite doit &ecirc;tre formul&eacute;e aupr&egrave;s de l&rsquo;agence impronon&ccedil;able, afin de proc&eacute;der &agrave; une <strong>n&eacute;gociation sp&eacute;cifique des droits</strong> (dur&eacute;e, territoire, type de diffusion, exclusivit&eacute;, etc.). Un avenant ou une nouvelle licence pourra alors &ecirc;tre d&eacute;livr&eacute;.</p>
<p style="text-align: left;"><strong>8.4. Cr&eacute;dits et respect du droit moral</strong></p>
<p style="text-align: left;">L&rsquo;utilisateur s&rsquo;engage &agrave; :</p>
<ul>
<li style="text-align: left;"><strong>ne pas supprimer les m&eacute;tadonn&eacute;es d&rsquo;auteur int&eacute;gr&eacute;es aux fichiers</strong>,</li>
<li style="text-align: left;"><strong>mentionner syst&eacute;matiquement les cr&eacute;dits photo ou vid&eacute;o</strong> lorsqu&rsquo;ils sont sp&eacute;cifi&eacute;s (ex. : <em>&copy; auteur / impronon&ccedil;able</em>),</li>
<li style="text-align: left;"><strong>respecter le droit moral des auteurs</strong>, incluant le droit au respect de l&rsquo;int&eacute;grit&eacute; de l&rsquo;&oelig;uvre (pas de recadrage abusif, de filtres d&eacute;naturants, ni de d&eacute;tournement du contexte original).</li>
</ul>
HTML,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'page_type' => 'licenses',
                'title' => 'Licences',
                'content' => <<<'HTML'
<h3 style="text-align: left;"><strong>1. Introduction</strong></h3>
<blockquote>
<p>Les fichiers accessibles sur Stimergie sont mis &agrave; disposition dans le cadre d&rsquo;une licence d&rsquo;utilisation d&eacute;finie contractuellement. Cette page pr&eacute;cise les usages autoris&eacute;s, les restrictions, et les d&eacute;marches &agrave; suivre en cas de diffusion &eacute;largie ou publicitaire.</p>
</blockquote>
<hr>
<h3 style="text-align: left;"><strong>2. Licence standard (par d&eacute;faut)</strong></h3>
<h3 style="text-align: left;"><strong>Usages autoris&eacute;s :</strong></h3>
<ul style="text-align: left;">
<li>Site internet, blog, intranet</li>
<li>R&eacute;seaux sociaux (publications organiques uniquement)</li>
<li>Newsletter interne ou externe</li>
<li>Pr&eacute;sentations, outils de formation, documents internes</li>
<li>Supports imprim&eacute;s (brochures, menus, fiches produit)</li>
</ul>
<h3 style="text-align: left;"><strong>Usages non inclus :</strong></h3>
<ul style="text-align: left;">
<li>Publicit&eacute; avec achat d&rsquo;espace (digital, print, affichage, TV, etc.)</li>
<li>Sponsoring ou contenus sponsoris&eacute;s</li>
<li>Retargeting, banni&egrave;res, pre-roll, marketing programmatique</li>
<li>Utilisation par un tiers non autoris&eacute; (agence, partenaire non list&eacute;)</li>
</ul>
<h3 style="text-align: left;"><strong>Mention obligatoire :</strong></h3>
<blockquote>
<p>Le cr&eacute;dit doit &ecirc;tre respect&eacute; :</p>
</blockquote>
<blockquote>
<p>&copy; [Nom de l&rsquo;auteur] / impronon&ccedil;able</p>
</blockquote>
<hr>
<h3 style="text-align: left;"><strong>3.</strong></h3>
<h3 style="text-align: left;"><strong>Licence &eacute;tendue (sur demande)</strong></h3>
<p style="text-align: left;">Certains usages sont possibles avec une licence &eacute;tendue, &agrave; n&eacute;gocier au cas par cas :</p>
<ul style="text-align: left;">
<li>Publicit&eacute; (tous m&eacute;dias)</li>
<li>Campagnes payantes sur r&eacute;seaux sociaux</li>
<li>Exclusions de territoire, exclusivit&eacute; temporaire</li>
</ul>
<p style="text-align: left;"><strong>Contactez-nous : <a href="mailto:contact@stimergie.fr">contact@stimergie.fr</a></strong></p>
<hr>
<h3 style="text-align: left;"><strong>4. Bonnes pratiques et rappels</strong></h3>
<ul>
<li style="text-align: left;">Ne renommez pas les fichiers : les noms permettent la tra&ccedil;abilit&eacute; et la gestion des droits.</li>
<li style="text-align: left;">Ne transf&eacute;rez pas de contenus hors de votre organisation sans autorisation.</li>
<li style="text-align: left;">En cas de doute, consultez-nous : nous sommes l&agrave; pour accompagner un usage fluide <strong>et conforme</strong>.</li>
</ul>
HTML,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'page_type' => 'about',
                'title' => 'À propos',
                'content' => <<<'HTML'
<h2>Stimergie est une plateforme française qui centralise, sécurise et valorise vos contenus de marque.</h2><p>Inspirée par l'intelligence collective - comme les fourmis qui construisent en laissant des traces utiles aux autres - elle transforme chaque image, chaque document et chaque vidéo en patrimoine vivant, structuré et partageable.</p><p>Créée par <strong>l'agence Imprononçable</strong>, spécialisée dans la communication culinaire et lifestyle, Stimergie est née d'un besoin concret : gérer au quotidien des milliers de fichiers visuels, respecter les droits d'auteur et préserver l'héritage des marques.</p><p>Pensée pour les équipes créatives, les agences et les entreprises, Stimergie offre un espace unique, simple et souverain pour organiser, retrouver et faire vivre vos contenus.</p><p>Notre mission : préserver votre patrimoine visuel, vous faire gagner du temps et révéler toute la valeur de vos contenus.</p><h2>Éditeur du site</h2><p><strong>SASU agence imprononçable</strong></p><p>75, rue Parmentier 93100 Montreuil</p><p><a target="_blank" rel="noopener noreferrer nofollow" class="text-primary underline" href="mailto:contact@imprononcable.com">contact@imprononcable.com</a></p><p><strong>Numéro SIRET :</strong> 95092197300018</p><p><strong>Code APE :</strong> 7021</p><p><strong>Capital social :</strong> 1000 euros</p><h2>Directeur de publication</h2><p>Guillaume CZERW</p><p>Président</p><h2>Hébergeur</h2><p>O2SWITCH</p><p>Chemin des Pardiaux 63000 Clermont-Ferrand</p><p><strong>Capital de 100000€ Siret 510 909 80700032</strong> <a target="_blank" rel="noopener noreferrer nofollow" class="text-primary underline" href="https://www.o2switch.fr/du-rgpd.pdf">https://www.o2switch.fr/du-rgpd.pdf</a></p><h2>Propriété intellectuelle</h2><p>L'ensemble de ce site relève de la législation française et internationale sur les droits d'auteur et la propriété intellectuelle. Tous les droits de reproduction sont réservés.</p><h2>Responsabilité</h2><p>L'éditeur s'efforce d'assurer l'exactitude et la mise à jour des informations diffusées sur ce site. Toutefois, il ne peut garantir l'exactitude, la précision ou l'exhaustivité des informations mises à disposition.</p><h2>Données personnelles</h2><p>Le traitement des données personnelles est décrit dans notre politique de confidentialité.</p><h2>Droit applicable</h2><p>Le présent site est soumis au droit français. Tout litige sera de la compétence exclusive des tribunaux français.</p>
HTML,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_pages');
    }
};
