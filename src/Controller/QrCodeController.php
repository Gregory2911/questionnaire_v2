<?php

namespace App\Controller;

use App\Service\SecurityService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/qr-code')]
class QrCodeController extends AbstractController
{

    private $client;

    public function __construct()
    {
        $this->client = HttpClient::create([
            'verify_peer' => false,
        ]);
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
        } catch (Exception $e) {
            return $this->render('exception/error.html.twig', [
                'code' => '500',
                'message' => 'Le message reçu est corrompu ou invalide : ' . $e->getMessage(),
            ]);
        }
        //call API to get the data related to the payload
        $data = json_decode($payload, true);

        $isV2 = true;
        $url = $_SERVER['APP_SERV_V2'] . '/api/external/questionnaires?token=' . rawurlencode($decoded);
        $response = $this->client->request(
            'GET',
            $url
        );

        $response = $response->toArray(false);
        if (!isset($response['message'])) {
            return $this->render('exception/error.html.twig', [
                'code' => '500',
                'message' => 'Récupération du questionnaire impossible'
            ]);
        }
        $interns = [];

        foreach ($response['message'] as $questionary) {
            $data = [
                "id" => $questionary["idStagiaire"],
                "name" => $questionary["stagiaire"] ? $questionary["stagiaire"]["displayedLabel"] : $questionary["client"]["displayedLabel"],
                "bRealise" => $questionary["bRealise"],
                "url" =>  $questionary["lien"]
            ];
            array_push($interns, $data);
        }

        return $this->render('qr_code/index.html.twig', [
            'encryption' => $encoded,
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
