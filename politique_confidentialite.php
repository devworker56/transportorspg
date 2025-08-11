<?php
require_once 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="fw-bold mb-4">Politique de Confidentialité</h1>
                    
                    <p class="mb-4">La présente politique de confidentialité définit et vous informe de la manière dont Transporteur Provincial Gabonais (TPG) utilise et protège les informations que vous nous transmettez lors de votre utilisation de notre plateforme.</p>
                    
                    <h2 class="h4 fw-bold mt-4">1. Collecte des données personnelles</h2>
                    <p>Nous pouvons collecter les informations suivantes :</p>
                    <ul>
                        <li>Nom et prénom</li>
                        <li>Coordonnées (email, téléphone, adresse)</li>
                        <li>Informations sur votre véhicule (pour les transporteurs)</li>
                        <li>Informations sur vos marchandises (pour les clients)</li>
                        <li>Données de connexion (adresse IP, logs)</li>
                    </ul>
                    
                    <h2 class="h4 fw-bold mt-4">2. Utilisation des données</h2>
                    <p>Les informations que nous recueillons sont utilisées pour :</p>
                    <ul>
                        <li>Fournir et améliorer nos services</li>
                        <li>Gérer votre compte utilisateur</li>
                        <li>Faciliter les transactions entre transporteurs et clients</li>
                        <li>Vous envoyer des notifications importantes</li>
                        <li>Améliorer notre service client</li>
                        <li>Respecter nos obligations légales</li>
                    </ul>
                    
                    <h2 class="h4 fw-bold mt-4">3. Protection des données</h2>
                    <p>
                        Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger vos données contre tout accès non autorisé, modification, divulgation ou destruction.
                    </p>
                    <p>
                        Les données sensibles (comme les mots de passe) sont chiffrées lors de leur transmission et de leur stockage.
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">4. Conservation des données</h2>
                    <p>
                        Nous conservons vos données personnelles aussi longtemps que nécessaire pour fournir nos services et pour nous conformer à nos obligations légales. Les données peuvent être archivées conformément aux délais légaux de prescription.
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">5. Partage des données</h2>
                    <p>
                        Nous ne vendons, n'échangeons ni ne transférons vos données personnelles à des tiers sans votre consentement, sauf :
                    </p>
                    <ul>
                        <li>Aux transporteurs/clients dans le cadre d'une transaction</li>
                        <li>Pour se conformer à la loi ou à une demande gouvernementale</li>
                        <li>Pour protéger nos droits ou la sécurité de notre plateforme</li>
                    </ul>
                    
                    <h2 class="h4 fw-bold mt-4">6. Vos droits</h2>
                    <p>Conformément à la loi gabonaise sur la protection des données, vous disposez des droits suivants :</p>
                    <ul>
                        <li>Droit d'accès à vos données personnelles</li>
                        <li>Droit de rectification des données inexactes</li>
                        <li>Droit à l'effacement dans certains cas</li>
                        <li>Droit de limitation du traitement</li>
                        <li>Droit d'opposition au traitement</li>
                    </ul>
                    <p>
                        Pour exercer ces droits, veuillez nous contacter à <?= ADMIN_EMAIL ?>.
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">7. Cookies</h2>
                    <p>
                        Notre site utilise des cookies pour améliorer votre expérience. Vous pouvez configurer votre navigateur pour refuser les cookies, mais certaines fonctionnalités du site pourraient ne plus fonctionner correctement.
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">8. Modifications de la politique</h2>
                    <p>
                        Nous nous réservons le droit de modifier cette politique de confidentialité à tout moment. Les modifications prendront effet dès leur publication sur le site.
                    </p>
                    
                    <h2 class="h4 fw-bold mt-4">9. Contact</h2>
                    <p>
                        Pour toute question concernant cette politique de confidentialité, veuillez nous contacter à :<br>
                        Email : <?= ADMIN_EMAIL ?><br>
                        Téléphone : <?= SUPPORT_PHONE ?>
                    </p>
                    
                    <p class="mt-5 text-muted">Dernière mise à jour : <?= date('d/m/Y') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>