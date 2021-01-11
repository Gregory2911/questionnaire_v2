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

class QuestionnaireController extends AbstractController
{
    /**
     * @Route("/{id}", name="questionnaire")
     */
    public function index($id = null)
    {
        
        if(isset($id))
        {                        
            // if (isset($test) && $test == '1')
            // {
            //     $Serveur_Formdev = 'webdevtest.form-dev.fr';
            //     $serveurTest = 1;
            // }
            // else
            // {
            //     $Serveur_Formdev = $_SERVER['APP_SERV'];
            //     $serveurTest = 0;
            // }
            $Serveur_Formdev = $_SERVER['APP_SERV'];
            
            // dump($Serveur_Formdev);
            // die();
            $json = @file_get_contents('http://'.$Serveur_Formdev.'/questionnaire/' . $id);
            
            if($json !== false)
            {
                $content = json_decode($json, true); 
                
                $infoQuestionnaire = extract($content['Questionnaire']);//crée les variables $question $nom $presentation $anonyme 

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
                foreach ($Question as $key => $value)
                {
                    $field = new Field();

                    $field->setId($value['Id'])
                          ->setLibelle($value['Libelle'])
                          ->setTypeQuestion($value['TypeQuestion'])
                          ->setAide($value['Aide'])
                          ->setObligatoire($value['Obligatoire'])
                          ->setBorneInf(intval($value['BorneInf']))
                          ->setBorneSup(intval($value['BorneSup']));
                          
                    if(isset($value['Choix']))
                    {
                        $field->setChoix($value['Choix']);
                    }
                    
                    $tabQuestion[$i] = $field;                   

                    $i++;
                }

                $questionnaire->setField($tabQuestion);                            

                if($questionnaire->getRealise() == false)
                {
                    return $this->render('questionnaire/index.html.twig', [                        
                        'questionnaire' => $questionnaire,
                        'servicename'   => $_SERVER['APP_NAME']
                    ]);
                }
                else
                {
                    // throw new Exception('Le questionnaire a déjà été envoyé.');
                    return $this->render('exception/error.html.twig',[
                        'error' => 'Le questionnaire a déjà été envoyé.'
                    ]);
                }

            }
            else
            {
                // throw new Exception('Récupération du questionnaire impossible.');
                return $this->render('exception/error.html.twig',[
                    'error' => 'Récupération du questionnaire impossible.'
                ]);
            }
        }
        else
        {            
            return $this->render('exception/error.html.twig',[
                'error' => 'Identifiant inconnu.'
            ]);
        }        
    }

    /**
     * @Route("/submitQuestionnaire/{id}", name="submitQuestionnaire")
     */
    public function submitQuestionnaire($id = null, Request $request)
    {
        // if (isset($test) && $test == '1')
        // {
        //     $Serveur_Formdev = 'webdevtest.form-dev.fr';
        //     $serveurTest = 1;
        // }
        // else
        // {
        //     $Serveur_Formdev = $_SERVER['APP_SERV'];
        //     $serveurTest = 0;
        // }
        
        $Serveur_Formdev = $_SERVER['APP_SERV'];

        if($id !== null)
        {
            
            $idQuestionnaire = $id;

            $request = Request::createFromGlobals();

            $isAnonyme = 0;
            
            $i = 0;
            $tabReponses = array();
            foreach ($request->request->all() as $key => $value) //$value est un array
            {
                $elt = explode("|",$key);
                
                if($elt[0] == 'id')
                {
                    if (!empty($value))
                    {
                        if($elt[2] == "vrai" && $value[0] == "")
                        {        
                            // throw new Exception('Vous devez répondre à toutes les questions obligatoires.');                    
                            return $this->render('exception/error.html.twig',[
                                'error' => 'Vous devez répondre à toutes les questions obligatoires.'
                            ]);
                        }
                        elseif($value[0] !== "")
                        {                    
                            $j =  0;
                            $tabReponse = array();
                            foreach ($value as $keyQuestion => $valueQuestion) 
                            {
                                if($elt[3] == "long")
                                {
                                    $reponse = new ReponseLongue();                                
                                    $reponse->setReponseLongue($valueQuestion);
                                    $tabReponse[$j] = $reponse;
                                }
                                else
                                {
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
                }
                elseif($key == 'anonyme')
                {                    
                    $isAnonyme = 1;
                }         
            }
            
            $encoders = [new JsonEncoder()];
            $normalizers = [new ObjectNormalizer()];

            $serializer = new Serializer($normalizers, $encoders);
                        
            $json = '{"isAnonyme":' . $isAnonyme . ',"Reponse":' . $serializer->serialize($tabReponses, 'json') . '}';                                   
            // dump($tabReponses);     
            // dump($json);
            // die();

            $options = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Content-Type: application/json",
                    'ignore_errors' => true,
                    'content' => $json
                )
            );
            
            $context = stream_context_create($options);
            
            $result = file_get_contents('http://'.$Serveur_Formdev .'/questionnaire/'. $id, false, $context);
            
            $reponseJson = json_decode($result, true);

            // dump($reponseJson);
            // die();
            
            if($reponseJson['ok'] !== false)
            {
                $reponseSubmit = "Votre questionnaire a bien été envoyé. Merci pour votre réponse.";
            }
            else
            {
                $reponseSubmit = $reponseJson['Erreur'];
            }

            return $this->render('questionnaire/submitQuestionnaire.html.twig', [
                'reponse' => $reponseSubmit
            ]);
        }
        else
        {            
            //throw new Exception('Identifiant inconnu.');
            return $this->render('exception/error.html.twig',[
                'error' => 'Identifiant inconnu.'
            ]);
        }
    }
}
