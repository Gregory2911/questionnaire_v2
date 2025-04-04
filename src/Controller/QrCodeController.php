<?php

namespace App\Controller;

use App\Service\SecurityService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(path: '/qr-code')]
class QrCodeController extends AbstractController
{

    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        // $this->client = HttpClient::create([
        //     'verify_peer' => false,
        // ]);
    }


    #[Route('', methods: ['GET'])]
    public function index(Request $request, SecurityService $securityService): Response
    {
        $encoded = $request->query->get('token');
        if (!$encoded) {
            return $this->render('exception/error.html.twig', [
                'code' => '400',
                'message' => 'Le paramètre d\'encryption est manquant ou invalide.',
            ]);
        }

        try {
            // Double décodage
            $decoded = rawurldecode($encoded);
            $payload = $securityService->decodeAndDecrypt($decoded, 'AES-256-CBC');

            // Vérification de l'intégrité du payload
            if (empty($payload)) {
                throw new Exception('Le message reçu est corrompu ou invalide.');
            }

            // Vérification de la validité du payload
            $payload = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Erreur dans le formatage des ressources.');
            }

            if (!$payload['idCampagneQuestionnaire'] || !$payload['idDossier']) {
                throw new Exception('Les information reçue son inexactes.');
            }
        } catch (Exception $e) {
            return $this->render('exception/error.html.twig', [
                'code' => '500',
                'message' => 'Le message reçu est corrompu ou invalide : ' . $e->getMessage(),
            ]);
        }

        // Récuperation de la liste des questionnaires
        $url = $_SERVER['APP_SERV_V2'] . '/api/external/questionnaires?token=' . rawurlencode($decoded);
        $response = $this->client->request(
            'GET',
            $url
        );

        $response = $response->toArray(false);
        if (!isset($response['message']) || !is_array($response['message'])) {
            return $this->render('exception/error.html.twig', [
                'code' => '500',
                'message' => 'Récupération du questionnaire impossible'
            ]);
        }
        $interns = [];

        $typeModelSurvey = null;
        foreach ($response['message'] as $questionary) {
            $typeModelSurvey = $questionary["modeleQuestionnaire"]["typeQuestionnaire"];
            if(!$typeModelSurvey){
                return $this->render('exception/error.html.twig', [
                    'code' => '500',
                    'message' => 'Le type de questionnaire est introuvable.'
                ]);
            }
            switch ($typeModelSurvey) {
                case 1:
                    $data = [
                        "id" => $questionary["idStagiaire"],
                        "name" => $questionary["stagiaire"] ? $questionary["stagiaire"]["displayedLabel"] : $questionary["client"]["displayedLabel"],
                        "bRealise" => $questionary["bRealise"],
                        "url" =>  $questionary["lien"]
                    ];
                    break;
                case 2:
                case 3:
                    $data = [
                        "id" => $questionary["idClient"],
                        "name" => $questionary["client"] ? $questionary["client"]["displayedLabel"] : $questionary["client"]["displayedLabel"],
                        "bRealise" => $questionary["bRealise"],
                        "url" =>  $questionary["lien"]
                    ];
                    break;
                case 4:
                    $data = [
                        "id" => $questionary["intervenant"]["idIntervenant"],
                        "name" => $questionary["intervenant"] ? $questionary["intervenant"]["prenomNom"] : $questionary["client"]["prenomNom"],
                        "bRealise" => $questionary["bRealise"],
                        "url" =>  $questionary["lien"]
                    ];
                    break;
                default:
                    return $this->render('exception/error.html.twig', [
                        'code' => '500',
                        'message' => 'Le type de questionnaire est introuvable.'
                    ]);
            }
            array_push($interns, $data);
        }

        return $this->render('qr_code/index.html.twig', [
            'encryption' => $encoded,
            'type' => $typeModelSurvey,
            'interns' => $interns,
        ]);
    }

    #[Route('/redirect', name: 'app_qr_code_questionary', methods: ['POST'])]
    public function redirectToQuestionary(Request $request): Response
    {
        $selectedInternDataJson = $request->request->get('selectedInternData');
        $selectedIntern = json_decode($selectedInternDataJson, true);

        if (!$selectedIntern) {
            return $this->render('error.html.twig', [
                'code' => '500',
                'message' => 'Erreur : Les données du stagiaire sélectionné sont introuvables.'
            ]);
        }

        if (!isset($selectedIntern['url'])) {
            return $this->render('error.html.twig', [
                'code' => '500',
                'message' => 'Erreur : Impossible de récupérer l\'URL du questionnaire pour le stagiaire sélectionné.'
            ]);
        }

        return $this->redirect($selectedIntern['url']);
    }
}
