<?php
/*********************************************************************
    canned.php

    Canned Responses aka Premade Responses.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require('staff.inc.php');
include_once(INCLUDE_DIR.'class.canned.php');

function canned_attachment_ids($canned) {
    $ids = array();

    if ($canned && $canned->attachments) {
        foreach ($canned->attachments as $attachment) {
            if (!$attachment->inline)
                $ids[$attachment->file_id] = true;
        }
    }

    return $ids;
}

function validate_canned_attachments($field, &$error, $existing=array()) {
    $error = null;
    $errors = array();
    $existing = array_fill_keys(array_keys($existing ?: array()), true);
    $config = $field->getConfiguration();
    $form_name = $field->getFormName();

    if ($form_name && isset($_FILES[$form_name])) {
        foreach (AttachmentFile::format($_FILES[$form_name]) ?: array() as $file) {
            if (!FileUploadField::isValidFile($file, $config['strictmimecheck'])) {
                $errors[] = sprintf('%s: %s',
                    Format::htmlchars($file['name']), __('Invalid File'));
            } elseif (!$field->isValidFileType($file['name'], $file['type'])) {
                $errors[] = sprintf('%s: %s',
                    Format::htmlchars($file['name']), __('File type is not allowed'));
            }
        }
    }

    $keepers = $field->getClean();
    $pending = array_diff_key($keepers ?: array(), $existing);

    if ($pending) {
        foreach (AttachmentFile::objects()->filter(array(
            'id__in' => array_keys($pending)
        )) as $file) {
            if (!$field->isValidFileType($file->getName(), $file->getType())) {
                $errors[] = sprintf('%s: %s',
                    Format::htmlchars($pending[$file->getId()] ?: $file->getName()),
                    __('File type is not allowed'));
            }
        }
    }

    if ($errors) {
        $error = implode('<br />', array_unique($errors));
        return false;
    }

    return $keepers;
}

if ($thisstaff && $roles = $thisstaff->getRoles()) {
    $cannedManage = array();
    foreach ($roles as $r) {
        if ($r->hasPerm(Canned::PERM_MANAGE, false))
            $cannedManage[] = 1;
    }
}

/* check permission */
if(!$thisstaff
        || !in_array(1, $cannedManage)
        || !$cfg->isCannedResponseEnabled()) {
    header('Location: kb.php');
    exit;
}

$canned=null;
if($_REQUEST['id'] && !($canned=Canned::lookup($_REQUEST['id'])))
    $errors['err']=sprintf(__('%s: Unknown or invalid ID.'), __('Canned Response'));
if ($canned && !$canned->staffCanAccess($thisstaff)) {
    header('Location: canned.php');
    exit;
}

$canned_form = new SimpleForm(array(
    'attachments' => new FileUploadField(array('id'=>'attach',
        'configuration'=>array(
            'extensions'=>$cfg->getAllowedFileTypes() ?: false,
            'size'=>$cfg->getMaxFileSize())
   )),
));

if ($canned
    && $canned->attachments
    && ($attachments = $canned_form->getField('attachments'))) {
     $attachments->setAttachments($canned->attachments);
}

if ($_POST) {
    switch(strtolower($_POST['do'])) {
        case 'update':
            if(!$canned) {
                $errors['err']=sprintf(__('%s: Unknown or invalid'), __('canned response'));
            } else {
                $keepers = validate_canned_attachments(
                    $canned_form->getField('attachments'),
                    $errors['files'],
                    canned_attachment_ids($canned)
                );
                if ($keepers !== false && $canned->update($_POST, $errors)) {
                    $msg=sprintf(__('Successfully updated %s.'),
                        __('this canned response'));

                    $type = array('type' => 'edited');
                    Signal::send('object.edited', $canned, $type);

                    $canned->attachments->keepOnlyFileIds($keepers, false);

                    $images = Draft::getAttachmentIds($_POST['response']);
                    $canned->attachments->keepOnlyFileIds($images, true);

                    Draft::deleteForNamespace('canned.'.$canned->getId());
                } elseif(!$errors['err']) {
                    $errors['err'] = $errors['files']
                        ?: sprintf('%s %s',
                            sprintf(__('Unable to update %s.'), __('this canned response')),
                            __('Correct any errors below and try again.'));
                }
            }
            break;
        case 'create':
            $premade = Canned::create();
            $keepers = validate_canned_attachments(
                $canned_form->getField('attachments'),
                $errors['files']
            );
            if ($keepers !== false && $premade->update($_POST,$errors)) {
                $msg=sprintf(__('Successfully added %s.'), Format::htmlchars($_POST['title']));
                $type = array('type' => 'created');
                Signal::send('object.created', $premade, $type);
                $_REQUEST['a']=null;
                if ($keepers)
                    $premade->attachments->upload($keepers);

                $premade->attachments->upload(
                    Draft::getAttachmentIds($_POST['response']), true);

                Draft::deleteForNamespace('canned', $thisstaff->getId());
            } elseif(!$errors['err']) {
                $errors['err'] = $errors['files']
                    ?: sprintf('%s %s',
                        sprintf(__('Unable to add %s.'), __('this canned response')),
                        __('Correct any errors below and try again.'));
            }
            break;
        case 'mass_process':
            if(!$_POST['ids'] || !is_array($_POST['ids']) || !count($_POST['ids'])) {
                $errors['err']=sprintf(__('You must select at least %s.'), __('one canned response'));
            } else {
                $count=count($_POST['ids']);
                switch(strtolower($_POST['a'])) {
                    case 'enable':
                        $sql='UPDATE '.CANNED_TABLE.' SET isenabled=1 '
                            .' WHERE canned_id IN ('.implode(',', db_input($_POST['ids'])).')';
                        if(db_query($sql) && ($num=db_affected_rows())) {
                            if($num==$count)
                                $msg = sprintf(__('Successfully enabled %s'),
                                    _N('selected canned response', 'selected canned responses', $count));
                            else
                                $warn = sprintf(__('%1$d of %2$d %3$s enabled'), $num, $count,
                                    _N('selected canned response', 'selected canned responses', $count));
                        } else {
                            $errors['err'] = sprintf(__('Unable to enable %s'),
                                _N('selected canned response', 'selected canned responses', $count));
                        }
                        break;
                    case 'disable':
                        $sql='UPDATE '.CANNED_TABLE.' SET isenabled=0 '
                            .' WHERE canned_id IN ('.implode(',', db_input($_POST['ids'])).')';
                        if(db_query($sql) && ($num=db_affected_rows())) {
                            if($num==$count)
                                $msg = sprintf(__('Successfully disabled %s'),
                                    _N('selected canned response', 'selected canned responses', $count));
                            else
                                $warn = sprintf(__('%1$d of %2$d %3$s disabled'), $num, $count,
                                    _N('selected canned response', 'selected canned responses', $count));
                        } else {
                            $errors['err'] = sprintf(__('Unable to disable %s'),
                                _N('selected canned response', 'selected canned responses', $count));
                        }
                        break;
                    case 'delete':

                        $i=0;
                        foreach($_POST['ids'] as $k=>$v) {
                            if(($c=Canned::lookup($v)) && $c->delete())
                                $i++;
                        }

                        if($i==$count)
                            $msg = sprintf(__('Successfully deleted %s.'),
                                _N('selected canned response', 'selected canned responses', $count));
                        elseif($i>0)
                            $warn=sprintf(__('%1$d of %2$d %3$s deleted'), $i, $count,
                                _N('selected canned response', 'selected canned responses', $count));
                        elseif(!$errors['err'])
                            $errors['err'] = sprintf(__('Unable to delete %s.'),
                                _N('selected canned response', 'selected canned responses', $count));
                        break;
                    default:
                        $errors['err']=__('Unknown action');
                }
            }
            break;
        default:
            $errors['err']=__('Unknown action');
            break;
    }
}

$page='cannedresponses.inc.php';
$tip_namespace = 'knowledgebase.canned_response';
if($canned || ($_REQUEST['a'] && !strcasecmp($_REQUEST['a'],'add'))) {
    $page='cannedresponse.inc.php';
}

$nav->setTabActive('kbase');
$ost->addExtraHeader('<meta name="tip-namespace" content="' . $tip_namespace . '" />',
    "$('#content').data('tipNamespace', '".$tip_namespace."');");
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.$page);
print $canned_form->getMedia();
include(STAFFINC_DIR.'footer.inc.php');
?>