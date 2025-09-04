<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use App\Entity\Field;
use App\Entity\Questionnaire;
use App\Entity\Reponses;
use App\Entity\Reponse;
use App\Entity\ReponseLongue;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class QuestionnaireController extends AbstractController
{

    public function __construct(private HttpClientInterface $httpClient)
    {}

    /**
     * @Route("/{id}", name="questionnaire")
     */
    public function index($id = null, Request $request)
    {
        if (isset($id)) {
            $isV2 = false;
            $Serveur_Formdev = $_SERVER['APP_SERV'];
            $url = $Serveur_Formdev . '/questionnaire/' . $id;
            
            if ($request->get('v2')) {
                // $Serveur_Formdev = $_SERVER['APP_SERV_V2'];
                // $url = $Serveur_Formdev . '/api/erp/questionnaire/get-by-url/' . $id;
                // $isV2 = true;
                $Serveur_Formdev = $_SERVER['APP_SERV_V2'];
                $url = $Serveur_Formdev . '/api/external/questionnaires/get-new-url/' . $id;
                // $url = 'http://127.0.0.1:8000/api/external/questionnaires/get-new-url/' . $id;
                // $json = @file_get_contents($url);
                $response = $this->httpClient->request('GET', $url);
                $response = $response->toArray(1);                               
                if (isset($response['link'])) {
                    return $this->redirect($response['link']);
                } else {
                    return $this->render('exception/error.html.twig', ['code' => '500', 'message' => 'Récupération du questionnaire impossible.']);
                }
            }

            $json = @file_get_contents($url);
            if ($json !== false) {
                $content = json_decode($json, true);
                if ($content['ok'] !== false) {
                    $infoQuestionnaire = extract($content['Questionnaire']); //crée les variables $question $nom $presentation $anonyme 
                    $questionnaire = new Questionnaire();
                    $questionnaire->setOk($content['ok'])
                        ->setCle($id)
                        ->setType($content['Type'])
                        ->setRealise($content['Realise'])
                        ->setNom($Nom)
                        ->setPresentation($Presentation)
                        ->setNomDestinataire($content['Nom'])
                        ->setPrenomDestinataire($content['Prenom'])
                        ->setAnonyme($Anonyme)
                        ->setSociete($content['NomSociete']);;

                    $i = 0;
                    $tabQuestion = array();
                    foreach ($Question as $key => $value) {
                        $field = new Field();

                        $field->setId($value['Id'])
                            ->setLibelle($value['Libelle'])
                            ->setTypeQuestion($value['TypeQuestion'])
                            ->setAide($value['Aide'])
                            ->setObligatoire($value['Obligatoire'])
                            ->setBorneInf(intval($value['BorneInf']))
                            ->setBorneSup(intval($value['BorneSup']));

                        if (isset($value['Choix'])) {
                            $field->setChoix($value['Choix']);
                        }

                        $tabQuestion[$i] = $field;

                        $i++;
                    }

                    $questionnaire->setField($tabQuestion);

                    if ($questionnaire->getRealise() == false) {
                        return $this->render('questionnaire/index.html.twig', [
                            'questionnaire' => $questionnaire,
                            'servicename'   => $_SERVER['APP_NAME'],
                            'isV2' => $isV2
                        ]);
                    } else {
                        // throw new Exception('Le questionnaire a déjà été envoyé.');
                        return $this->render('exception/error.html.twig', [
                            'code' => '500',
                            'message' => 'Le questionnaire a déjà été envoyé.'
                        ]);
                    }
                } else {
                    return $this->render('exception/error.html.twig', [
                        'code' => '500',
                        'message' => 'Récupération du questionnaire impossible.'
                    ]);
                }
            } else {
                return $this->render('exception/error.html.twig', [
                    'code' => '500',
                    'message' => 'Récupération du questionnaire impossible.'
                ]);
            }
        } else {
            return $this->render('exception/error.html.twig', [
                'code' => '500',
                'message' => 'Identifiant inconnu.'
            ]);
        }
    }

    /**
     * @Route("/submitQuestionnaire/{id}", name="submitQuestionnaire")
     */
    public function submitQuestionnaire($id = null, Request $request)
    {
        $Serveur_Formdev = $_SERVER['APP_SERV'];
        if ($id !== null) {
            $request = Request::createFromGlobals();
            $isAnonyme = 0;
            $i = 0;
            $tabReponses = array();
            foreach ($request->request->all() as $key => $value) {
                $elt = explode("|", $key);

                if ($elt[0] == 'id') {
                    if (!empty($value)) {
                        if ($elt[2] == "vrai" && $value[0] == "") {
                            return $this->render('exception/error.html.twig', [
                                'code' => '500',
                                'message' => 'Vous devez répondre à toutes les questions obligatoires.'
                            ]);
                        } elseif ($value[0] !== "") {
                            $j =  0;
                            $tabReponse = array();
                            foreach ($value as $keyQuestion => $valueQuestion) {
                                if ($elt[3] == "long") {
                                    $reponse = new ReponseLongue();
                                    $reponse->setReponseLongue($valueQuestion);
                                    $tabReponse[$j] = $reponse;
                                } else {
                                    $reponse = new Reponse();
                                    $reponse->setReponse($valueQuestion);
                                    $tabReponse[$j] = $reponse;
                                }
                                $j++;
                            }
                            $reponses = new Reponses();
                            $reponses->setId(intval($elt[1]))
                                ->setReponses($tabReponse);

                            $tabReponses[$i] = $reponses;
                            $i++;
                        }
                    }
                } elseif ($key == 'anonyme') {
                    $isAnonyme = 1;
                }
            }

            $encoders = [new JsonEncoder()];
            $normalizers = [new ObjectNormalizer()];

            $serializer = new Serializer($normalizers, $encoders);

            $json = '{"isAnonyme":' . $isAnonyme . ',"Reponse":' . $serializer->serialize($tabReponses, 'json') . '}';
            $options = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Content-Type: application/json",
                    'ignore_errors' => true,
                    'content' => $json
                )
            );

            $context = stream_context_create($options);

            $result = file_get_contents($Serveur_Formdev . '/questionnaire/' . $id, false, $context);

            $reponseJson = json_decode($result, true);

            if ($reponseJson['ok'] !== false) {
                $reponseSubmit = "Votre questionnaire a bien été envoyé. Merci pour votre réponse.";
            } else {
                $reponseSubmit = $reponseJson['Erreur'];
            }

            return $this->render('questionnaire/submitQuestionnaire.html.twig', [
                'reponse' => $reponseSubmit
            ]);
        } else {
            //throw new Exception('Identifiant inconnu.');
            return $this->render('exception/error.html.twig', [
                'code' => '500',
                'message' => 'Identifiant inconnu.'
            ]);
        }
    }

    /**
     * @Route("/submitQuestionnaireV2/{id}", name="submitQuestionnaireV2")
     */
    public function submitQuestionnaireV2($id = null, Request $request)
    {
        $Serveur_Formdev = $_SERVER['APP_SERV_V2'];

        if ($id !== null) {
            $idQuestionnaire = $id;
            $isAnonyme = 0;
            $i = 0;
            $tabReponses = array();
            foreach ($request->request->all() as $key => $value) {
                $elt = explode("|", $key);
                if ($elt[0] == 'id') {
                    if (!empty($value)) {
                        if ($elt[2] == "vrai" && $value[0] == "") {
                            return $this->render('exception/error.html.twig', [
                                'code' => '500',
                                'message' => 'Vous devez répondre à toutes les questions obligatoires.'
                            ]);
                        } else if ($value[0] !== "") {
                            $j =  0;
                            $tabReponse = array();
                            foreach ($value as $keyQuestion => $valueQuestion) {
                                if ($elt[3] == "long") {
                                    $reponse = new ReponseLongue();
                                    $reponse->setReponseLongue($valueQuestion);
                                    $tabReponse[$j] = $reponse;
                                } else {
                                    $reponse = new Reponse();
                                    $reponse->setReponse($valueQuestion);
                                    $tabReponse[$j] = $reponse;
                                }
                                $j++;
                            }
                            $reponses = new Reponses();
                            $reponses->setId(intval($elt[1]))
                                ->setReponses($tabReponse);

                            $tabReponses[$i] = $reponses;

                            $i++;
                        }
                    }
                } else if ($key == 'anonyme') {
                    $isAnonyme = 1;
                }
            }

            $encoders = [new JsonEncoder()];
            $normalizers = [new ObjectNormalizer()];

            $serializer = new Serializer($normalizers, $encoders);

            $json = '{"isAnonyme":' . $isAnonyme . ',"Reponse":' . $serializer->serialize($tabReponses, 'json') . '}';

            $options = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Content-Type: application/json",
                    'ignore_errors' => true,
                    'content' => $json
                )
            );

            $context = stream_context_create($options);

            $result = file_get_contents($Serveur_Formdev . '/api/erp/questionnaire/validation/' . $id, false, $context);

            $reponseJson = json_decode($result, true);

            if ($reponseJson['ok'] !== false) {
                $reponseSubmit = "Votre questionnaire a bien été envoyé. Merci pour votre réponse.";
            } else {
                $reponseSubmit = $reponseJson['Erreur'];
            }

            return $this->render('questionnaire/submitQuestionnaire.html.twig', [
                'reponse' => $reponseSubmit
            ]);
        } else {
            //throw new Exception('Identifiant inconnu.');
            return $this->render('exception/error.html.twig', [
                'code' => '500',
                'message' => 'Identifiant inconnu.'
            ]);
        }
    }
}
