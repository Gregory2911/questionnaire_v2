<?php

namespace App\Controller;

use App\Service\SecurityService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QrCodeController extends AbstractController
{

    private $client;

    public function __construct()
    {
        $this->client = HttpClient::create([
            'verify_peer' => false,
        ]);
    }


    #[Route('/qr-code/{encryption}', name: 'app_qr_code', methods: ['GET'])]
    public function index(Request $request, SecurityService $securityService): Response
    {
        $decoded = urldecode(urldecode($request->get('encryption')));
        try {
            $payload = $securityService->decodeAndDecrypt($decoded, 'AES-256-CBC');
        } catch (Exception $e) {
            return $this->render('exception/error.html.twig', [
                'error' => 'Le message recu est corrumpu'
            ]);
        }
        //call API to get the data related to the payload
        $data = json_decode($payload, true);

        $isV2 = true;
        $url = $_SERVER['APP_SERV_V2'] . '/api/external/questionnaires/' . urlencode(urlencode($decoded)) . '/learners';
        $response = $this->client->request(
            'GET',
            $url
        );
        $response = $response->toArray(false);
        if (!isset($response['message'])) {
            return $this->render('exception/error.html.twig', [
                'code' => 500,
                'message' => 'Récupération du questionnaire impossible'
            ]);
        }
        $interns = [];

        foreach ($response['message'] as $questionary) {
            $data = [
                "id" => $questionary["idStagiaire"],
                "name" => $questionary["stagiaire"] ? $questionary["stagiaire"]["displayedLabel"] : $questionary["client"]["displayedLabel"],
                "url" =>  $questionary["lien"]
            ];
            array_push($interns, $data);
        }

        return $this->render('qr_code/index.html.twig', [
            'encryption' => $request->get('encryption'),
            'interns' => $interns,
        ]);
    }

    #[Route('/qr-code/{encryption}/redirect', name: 'app_qr_code_questionary', methods: ['POST'])]
    public function redirectToQuestionary(Request $request): Response
    {
        $selectedInternDataJson = $request->request->get('selectedInternData');
        $selectedIntern = json_decode($selectedInternDataJson, true);

        if (!$selectedIntern) {
            return $this->render('error_custom.html.twig', [
                'code' => '500',
                'message' => 'Erreur : Les données du stagiaire sélectionné sont introuvables.'
            ]);
        }

        if (!isset($selectedIntern['url'])) {
            return $this->render('error_custom.html.twig', [
                'code' => '500',
                'message' => 'Erreur : Impossible de récupérer l\'URL du questionnaire pour le stagiaire sélectionné.'
            ]);
        }

        return $this->redirect($selectedIntern['url']);
    }
}
