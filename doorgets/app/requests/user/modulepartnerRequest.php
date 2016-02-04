<?php

/*******************************************************************************
/*******************************************************************************
    doorGets 7.0 - 01, February 2016
    doorgets it's free PHP Open Source CMS PHP & MySQL
    Copyright (C) 2012 - 2015 By Mounir R'Quiba -> Crazy PHP Lover
    
/*******************************************************************************

    Website : http://www.doorgets.com
    Contact : http://www.doorgets.com/t/en/?contact
    
/*******************************************************************************
    -= One life, One code =-
/*******************************************************************************
    
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
******************************************************************************
******************************************************************************/


class ModulePartnerRequest extends doorGetsUserModuleRequest{
    
    public function __construct(&$doorGets) {
        
        parent::__construct($doorGets);
        
    }
    
    public function doAction() {
        
        try {
            
            $out = '';
            $cName = $this->doorGets->controllerNameNow();
            
            // Init langue 
            $lgActuel       = $this->doorGets->getLangueTradution();
            $moduleInfos    = $this->doorGets->moduleInfos($this->doorGets->Uri,$lgActuel);

            $User           = $this->doorGets->user;

            // Check if is content modo
            $is_modo = (in_array($moduleInfos['id'], $User['liste_module_modo']))?true:false;

            // Check if is module modo
            (
                in_array('module', $User['liste_module_interne'])  
                && in_array('module_'.$moduleInfos['type'],  $User['liste_module_interne'])

            ) ? $is_modules_modo = true : $is_modules_modo = false;

            // check if user can edit content
            $user_can_edit = (in_array($moduleInfos['id'], $User['liste_module_edit']))?true:false;

            // check if user can delete content
            $user_can_delete = (in_array($moduleInfos['id'], $User['liste_module_delete']))?true:false;
            
            // Init url redirection 
            $redirectUrl = './?controller=module'.$moduleInfos['type'].'&uri='.$this->doorGets->Uri.'&lg='.$lgActuel;
            
            // get Content for edit / delete
            $params = $this->doorGets->Params();
            if (array_key_exists('id',$params['GET'])) {
                
                $id = $params['GET']['id'];
                $isContent = $this->doorGets->dbQS($id,$this->doorGets->Table);
                
                if (!empty($isContent)) {
                    
                    if ($lgGroupe = @unserialize($isContent['groupe_traduction'])) {
                        
                        $idLgGroupe = $lgGroupe[$lgActuel];
                        
                        $isContentTraduction = $this->doorGets->dbQS($idLgGroupe,$this->doorGets->Table.'_traduction');
                        if (!empty($isContentTraduction)) {
                            
                            $isContent = array_merge($isContent,$isContentTraduction);
                            
                        }
                        
                        // test if user can edit content
                        if (
                            $isContent['id_user'] !== $this->doorGets->user['id']
                            && !in_array($isContent['id_groupe'], $this->doorGets->user['liste_enfant_modo'])
                       ) {
                            FlashInfo::set($this->doorGets->__("Vous n'avez pas les droits pour afficher ce contenu"),"error");
                            $this->doorGets->_redirect($redirectUrl);
                        }
                    }
                    
                }
                
            }
            
            $champsNonObligatoire = array('',);
            
            $messageSuccess = $this->doorGets->__("Vos informations ont bien été mises à jour");
            
            switch($this->Action) {
                
                case 'add':
                    
                    $this->doorGets->checkMode();
                    
                    if (!empty($this->doorGets->Form->i)) {
                        
                        $cResultsInt = $this->doorGets->getCountTable($this->doorGets->Table);
                        
                        // gestion des champs vide
                        foreach($this->doorGets->Form->i as $k=>$v) {
                            
                            if ( !in_array($k,$champsNonObligatoire) &&  empty($v) ) {
                                
                                $this->doorGets->Form->e[$cName.'_add_'.$k] = 'ok';
                            }
                        }
                        
                        $var = $this->doorGets->Form->i['url'];
                        $varUrl = filter_var($var,FILTER_VALIDATE_URL);
                        
                        if (empty($varUrl)) {
                            
                            $this->doorGets->Form->e[$cName.'_add_url'] = 'ok';
                            
                        }
                        
                        // validation si aucune erreur
                        if (empty($this->doorGets->Form->e)) {
                            
                            if (!array_key_exists('active',$this->doorGets->Form->i)) {
                                $this->doorGets->Form->i['active'] = 0;
                            }
                            //
                            
                            $data['pseudo']         = $User['pseudo'];
                            $data['id_user']        = $this->doorGets->user['id'];
                            $data['id_groupe']      = $this->doorGets->user['groupe'];
                            
                            $data['ordre']          = $cResultsInt + 1 ;
                            $data['active']         = (!$is_modo) ? 3 : $this->doorGets->Form->i['active'];
                            
                            $data['date_creation']  = time();
                            
                            $idContent = $this->doorGets->dbQI($data,$this->doorGets->Table);
                            
                            //
                            
                            foreach($this->doorGets->getAllLanguages() as $k=>$v) {
                                
                                $dataNext = array(
                                    'image' => $this->doorGets->Form->i['image'],
                                    'titre' => $this->doorGets->Form->i['titre'],
                                    'description' => $this->doorGets->Form->i['description'],
                                    'url' => $this->doorGets->Form->i['url'],
                                );

                                $dataNext['date_modification']  = $data['date_creation'];
                                $dataNext['id_content']     = $idContent;
                                $dataNext['langue']         = $k;
                                $idTraduction[$k]           = $this->doorGets->dbQI($dataNext,$this->doorGets->Table.'_traduction');
                                
                            }
                            
                            // Copy image to real path
                            $this->doorGets->copyFileToRealPath($this->doorGets->Uri,$this->doorGets->Form->i['image']);

                            $dataModification['groupe_traduction'] = serialize($idTraduction);
                            $this->doorGets->dbQU($idContent,$dataModification,$this->doorGets->Table);
                            
                            // Tracker
                            $usersTracking = new UsersTrackEntity(null,$this->doorGets);
                            $usersTracking->setIdSession(session_id())
                                ->setIpUser($_SERVER['REMOTE_ADDR'])
                                ->setUrlPage($_SERVER['REQUEST_URI'])
                                ->setUrlReferer($_SERVER['HTTP_REFERER'])
                                ->setIdUser($User['id'])
                                ->setTitle($dataNext['titre'])
                                ->setIdGroupe($User['groupe'])
                                ->setLangue($lgActuel)
                                ->setUriModule($this->doorGets->Uri)
                                ->setIdContent($idContent)
                                ->setAction($this->Action)
                                ->setDate(time())
                                ->save();

                            if (!$is_modo) {
                                
                                $moderation = new ModerationEntity(null,$this->doorGets);
                                $moderation->setIdContent($idContent)
                                    ->setIdUser($User['id'])
                                    ->setPseudo($User['pseudo'])
                                    ->setIdGroupe($User['groupe'])
                                    ->setUriModule($this->doorGets->Uri)
                                    ->setTypeModule('partner')
                                    ->setAction($this->Action)
                                    ->setLangue($lgActuel)
                                    ->setDateCreation(time())
                                    ->save();

                                $this->doorGets->sendEmailNotificationToGroupe(
                                    $moduleInfos['uri_notification_moderator'],
                                    $moduleInfos['id']
                                );

                                $messageSuccess = $this->doorGets->__("Votre contenu est en cours de modération");
                            }

                            $this->doorGets->successHeaderResponse(
                                $messageSuccess,
                                $redirectUrl
                            );
                            
                        }
                        
                        $this->doorGets->errorHeaderResponse(
                            $this->doorGets->__("Veuillez remplir correctement le formulaire"),
                            $this->doorGets->Form->e
                        );
                        
                    }
                    
                    break;
                
                case 'edit':
                    
                    if (!empty($this->doorGets->Form->i)) {
                        
                        $this->doorGets->checkMode();
                        
                        $listToCategories = '';
                        // gestion des champs vide
                        foreach($this->doorGets->Form->i as $k=>$v) {
                            
                            if ( !in_array($k,$champsNonObligatoire) && $k !== 'image' && empty($v) ) {
                                
                                $this->doorGets->Form->e[$cName.'_edit_'.$k] = 'ok';
                            }
                        }
                        
                        if (empty($this->doorGets->Form->i['image'])) {
                            $this->doorGets->Form->i['image'] = $isContent['image'];
                        }

                        if (empty($this->doorGets->Form->e)) {
                            
                            // Copy image to real path
                            $this->doorGets->copyFileToRealPath($this->doorGets->Uri,$this->doorGets->Form->i['image']);

                            $dataContenu['active'] = (!$is_modo) ? 3 : $this->doorGets->Form->i['active'];
                            
                            $dataTraduction = array(
                                'image'         => $this->doorGets->Form->i['image'],
                                'titre'         => $this->doorGets->Form->i['titre'],
                                'description'   => $this->doorGets->Form->i['description'],
                                'url'           => $this->doorGets->Form->i['url'],
                            );
                            
                            $dataVersion = $dataTraduction;
                            $dataVersion['active'] = $this->doorGets->Form->i['active'];
                            
                            $this->saveLastContentVersion($isContent['id_content'],$dataVersion);

                            // Tracker
                            $usersTracking = new UsersTrackEntity(null,$this->doorGets);
                            $usersTracking->setIdSession(session_id())
                                ->setIpUser($_SERVER['REMOTE_ADDR'])
                                ->setUrlPage($_SERVER['REQUEST_URI'])
                                ->setUrlReferer($_SERVER['HTTP_REFERER'])
                                ->setIdUser($User['id'])
                                ->setTitle($dataTraduction['titre'])
                                ->setIdGroupe($User['groupe'])
                                ->setLangue($lgActuel)
                                ->setUriModule($this->doorGets->Uri)
                                ->setIdContent($isContent['id_content'])
                                ->setAction($this->Action)
                                ->setDate(time())
                                ->save();

                            if (!$is_modo) {
                                
                                $moderation = new ModerationEntity(null,$this->doorGets);
                                $moderation->setIdContent($isContent['id_content'])
                                    ->setIdUser($User['id'])
                                    ->setPseudo($User['pseudo'])
                                    ->setIdGroupe($User['groupe'])
                                    ->setUriModule($this->doorGets->Uri)
                                    ->setTypeModule('blog')
                                    ->setAction($this->Action)
                                    ->setLangue($lgActuel)
                                    ->setDateCreation(time())
                                    ->save();

                                $this->doorGets->sendEmailNotificationToGroupe(
                                    $moduleInfos['uri_notification_moderator'],
                                    $moduleInfos['id']
                                );

                                $messageSuccess = $this->doorGets->__("Votre contenu est en cours de modération");
                                
                            } else {

                                $uri_module = $this->doorGets->Uri;
                                $id_content = $isContent['id_content'];

                                $this->doorGets->dbQL("
                                    DELETE FROM _moderation 
                                    WHERE id_content = '$id_content' 
                                    AND uri_module = '$uri_module'
                                    LIMIT 1000
                                ");

                                $uriNotification = ($dataContenu['active'] === '2') ? 
                                    $moduleInfos['uri_notification_user_success'] : 
                                    $moduleInfos['uri_notification_user_error']
                                ;
                                
                                $this->doorGets->sendEmailNotificationToUser(
                                    $uriNotification,
                                    $isContent['id_user']

                                );
                            }
                            
                            // Update Data
                            $this->doorGets->dbQU(
                                $isContent['id_content'],
                                $dataContenu,
                                $this->doorGets->Table
                            );

                            $this->doorGets->dbQU(
                                $isContent['id'],
                                $dataTraduction,
                                $this->doorGets->Table.'_traduction'
                            );

                            $this->doorGets->successHeaderResponse(
                                $messageSuccess
                            );

                        }
                        
                        $this->doorGets->errorHeaderResponse(
                            $this->doorGets->__("Veuillez remplir correctement le formulaire"),
                            $this->doorGets->Form->e
                        );
                    }
                    
                    break;
                
                case 'delete':
                    
                    if (!empty($this->doorGets->Form->i)) {
                        
                        $this->doorGets->checkMode();
                        
                        if (empty($this->doorGets->Form->e)) {
                            
                            $lgGroupe = unserialize($isContent['groupe_traduction']);
                            foreach($lgGroupe as $v) {
                                @$this->doorGets->dbQD($v,$this->doorGets->Table.'_traduction');
                            }
                            
                            @unlink(BASE.'data/'.$this->doorGets->getRealUri($this->doorGets->Uri).'/'.$isContent['image']);
                            
                            $this->doorGets->dbQD($isContent['id_content'],$this->doorGets->Table);
                            $this->doorGets->dbQL("DELETE FROM _dg_comments WHERE uri_module = '".$this->doorGets->Uri."' AND uri_content = '".$isContent['id_content']."' ");
                            $this->doorGets->dbQL("UPDATE ".$this->doorGets->Table." SET ordre = ordre - 1 WHERE ordre > ".$isContent['ordre']." ");
                            
                            // Tracker
                            $usersTracking = new UsersTrackEntity(null,$this->doorGets);
                            $usersTracking->setIdSession(session_id())
                                ->setIpUser($_SERVER['REMOTE_ADDR'])
                                ->setUrlPage($_SERVER['REQUEST_URI'])
                                ->setUrlReferer($_SERVER['HTTP_REFERER'])
                                ->setIdUser($User['id'])
                                ->setTitle($isContent['titre'])
                                ->setIdGroupe($User['groupe'])
                                ->setLangue($lgActuel)
                                ->setUriModule($this->doorGets->Uri)
                                ->setIdContent($isContent['id_content'])
                                ->setAction($this->Action)
                                ->setDate(time())
                                ->save();


                            FlashInfo::set($this->doorGets->__("Les données sont supprimées"));
                            $this->doorGets->_redirect($redirectUrl);
                        }
                        
                    }
                    
                    break;

            }  

        } catch (Exception $e) {
        
        }  
    }
}
