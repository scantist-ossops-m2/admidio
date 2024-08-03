<?php
/**
 ***********************************************************************************************
 * Save organization preferences
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode     : save           - Save organization preferences
 *            html_form      - Returns the html of the requested form
 *            new_org_dialog - show welcome dialog for new organization
 *            new_org_create - Create basic data for new organization in database
 *            htaccess       - set directory protection, write htaccess
 *            test_email     - send test email
 *            backup         - create backup of Admidio database
 * form     : The name of the form preferences that were submitted.
 ***********************************************************************************************
 */

use Admidio\UserInterface\Form;
use Admidio\UserInterface\Preferences;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('save', 'html_form', 'new_org_dialog', 'new_org_create', 'htaccess', 'test_email', 'backup')));
    $getForm = admFuncVariableIsValid($_GET, 'form', 'string');

    // only administrators are allowed to edit organization preferences or create new organizations
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    /**
     * @param string $folder
     * @param string $templateName
     * @return string
     */
    function getTemplateFileName(string $folder, string $templateName): string
    {
        // get all files from the folder
        $files = array_keys(FileSystemUtils::getDirectoryContent($folder, false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));
        $templateFileName = '';

        foreach ($files as $fileName) {
            if ($templateName === ucfirst(preg_replace('/[_-]/', ' ', str_replace(array('.tpl', '.html', '.txt'), '', $fileName)))) {
                $templateFileName = $fileName;
            }
        }
        return $templateFileName;
    }

    switch ($getMode) {
        case 'save':
            if (isset($_SESSION['preferences' . $getForm . 'Form'])) {
                $form = $_SESSION['preferences' . $getForm . 'Form'];
                $form->validate($_POST);
            } else {
                throw new AdmException('SYS_INVALID_PAGE_VIEW');
            }

            // first check the fields of the submitted form
            switch ($getForm) {
                case 'Common':
                    if (!StringUtils::strIsValidFolderName($_POST['theme'])
                        || !is_file(ADMIDIO_PATH . FOLDER_THEMES . '/' . $_POST['theme'] . '/index.html')) {
                        throw new AdmException('ORG_INVALID_THEME');
                    }
                    break;

                case 'Security':
                    if (!isset($_POST['enable_auto_login']) && $gSettingsManager->getBool('enable_auto_login')) {
                        // if auto login was deactivated than delete all saved logins
                        $sql = 'DELETE FROM ' . TBL_AUTO_LOGIN;
                        $gDb->queryPrepared($sql);
                    }
                    break;

                case 'RegionalSettings':
                    if (!StringUtils::strIsValidFolderName($_POST['system_language'])
                        || !is_file(ADMIDIO_PATH . FOLDER_LANGUAGES . '/' . $_POST['system_language'] . '.xml')) {
                        throw new AdmException('SYS_FIELD_EMPTY', array('SYS_LANGUAGE'));
                    }
                    break;

                case 'Messages':
                    // get real filename of the template file
                    if ($_POST['mail_template'] !== $gSettingsManager->getString('mail_template')) {
                        $_POST['mail_template'] = getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates', $_POST['mail_template']);
                    }
                    break;

                case 'Photos':
                    // get real filename of the template file
                    if ($_POST['photo_ecard_template'] !== $gSettingsManager->getString('photo_ecard_template')) {
                        $_POST['photo_ecard_template'] = getTemplateFileName(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates', $_POST['photo_ecard_template']);
                    }
                    break;
            }

            // then update the database with the new values

            foreach ($_POST as $key => $value) { // TODO possible security issue
                // Sort out elements that are not stored in adm_preferences here
                if (!in_array($key, array('save', 'admidio-csrf-token'))) {
                    if (str_starts_with($key, 'org_')) {
                        $gCurrentOrganization->setValue($key, $value);
                    } elseif (str_starts_with($key, 'SYSMAIL_')) {
                        $text = new TableText($gDb);
                        $text->readDataByColumns(array('txt_org_id' => $gCurrentOrgId, 'txt_name' => $key));
                        $text->setValue('txt_text', $value);
                        $text->save();
                    } elseif ($key === 'enable_auto_login' && $value == 0 && $gSettingsManager->getBool('enable_auto_login')) {
                        // if deactivate auto login than delete all saved logins
                        $sql = 'DELETE FROM ' . TBL_AUTO_LOGIN;
                        $gDb->queryPrepared($sql);
                        $gSettingsManager->set($key, $value);
                    } else {
                        $gSettingsManager->set($key, $value);
                    }
                }
            }

            // now save all data
            $gCurrentOrganization->save();

            // refresh language if necessary
            if ($gL10n->getLanguage() !== $gSettingsManager->getString('system_language')) {
                $gL10n->setLanguage($gSettingsManager->getString('system_language'));
            }

            // clean up
            $gCurrentSession->reloadAllSessions();

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_SAVE_DATA')));
            break;

        // Returns the html of the requested form
        case 'html_form':
            $preferencesUI = new Preferences('preferencesForm');
            $methodName = 'create' . $getForm . 'Form';
            echo $preferencesUI->{$methodName}();
            break;

        // show welcome dialog for new organization
        case 'new_org_dialog':
            if (isset($_SESSION['add_organization_request'])) {
                $formValues = $_SESSION['add_organization_request'];
                unset($_SESSION['add_organization_request']);
            } else {
                $formValues['orgaShortName'] = '';
                $formValues['orgaLongName'] = '';
                $formValues['orgaEmail'] = '';
            }

            $headline = $gL10n->get('INS_ADD_ORGANIZATION');

            // create html page object
            $page = new HtmlPage('admidio-new-organization', $headline);

            // add current url to navigation stack
            $gNavigation->addUrl(CURRENT_URL, $headline);

            $page->addHtml('<p class="lead">' . $gL10n->get('ORG_NEW_ORGANIZATION_DESC') . '</p>');

            // show form
            $form = new HtmlForm('add_new_organization_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'new_org_create')), $page);
            $form->addInput(
                'orgaShortName',
                $gL10n->get('SYS_NAME_ABBREVIATION'),
                $formValues['orgaShortName'],
                array('maxLength' => 10, 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
            );
            $form->addInput(
                'orgaLongName',
                $gL10n->get('SYS_NAME'),
                $formValues['orgaLongName'],
                array('maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
            );
            $form->addInput(
                'orgaEmail',
                $gL10n->get('SYS_EMAIL_ADMINISTRATOR'),
                $formValues['orgaEmail'],
                array('type' => 'email', 'maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
            );
            $form->addSubmitButton(
                'btn_forward',
                $gL10n->get('INS_SET_UP_ORGANIZATION'),
                array('icon' => 'bi-wrench')
            );

            // add form to html page and show page
            $page->addHtml($form->show());
            $page->show();
            break;

        // Create basic data for new organization in database
        case 'new_org_create':
            $_SESSION['add_organization_request'] = $_POST;

            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

            // form fields are not filled
            if ($_POST['orgaShortName'] === '' || $_POST['orgaLongName'] === '') {
                throw new AdmException('INS_ORGANIZATION_NAME_NOT_COMPLETELY');
            }

            // check if orga shortname exists
            $organization = new Organization($gDb, $_POST['orgaShortName']);
            if ($organization->getValue('org_id') > 0) {
                throw new AdmException('INS_ORGA_SHORTNAME_EXISTS', array($_POST['orgaShortName']));
            }

            // allow only letters, numbers and special characters like .-_+@
            if (!StringUtils::strValidCharacters($_POST['orgaShortName'], 'noSpecialChar')) {
                throw new AdmException('SYS_FIELD_INVALID_CHAR', array('SYS_NAME_ABBREVIATION'));
            }

            // set execution time to 2 minutes because we have a lot to do
            PhpIniUtils::startNewExecutionTimeLimit(120);

            $gDb->startTransaction();

            // create new organization
            $newOrganization = new Organization($gDb, $_POST['orgaShortName']);
            $newOrganization->setValue('org_longname', $_POST['orgaLongName']);
            $newOrganization->setValue('org_shortname', $_POST['orgaShortName']);
            $newOrganization->setValue('org_homepage', ADMIDIO_URL);
            $newOrganization->save();

            // write all preferences from preferences.php in table adm_preferences
            require_once(ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/preferences.php');

            // set some specific preferences whose values came from user input of the installation wizard
            $defaultOrgPreferences['email_administrator'] = $_POST['orgaEmail'];
            $defaultOrgPreferences['system_language'] = $gSettingsManager->getString('system_language');

            // create all necessary data for this organization
            $settingsManager =& $newOrganization->getSettingsManager();
            $settingsManager->setMulti($defaultOrgPreferences, false);
            $newOrganization->createBasicData($gCurrentUserId);

            // now refresh the session organization object because of the new organization
            $currentOrganizationId = $gCurrentOrgId;
            $gCurrentOrganization = new Organization($gDb, $currentOrganizationId);

            // if installation of second organization than show organization select at login
            if ($gCurrentOrganization->countAllRecords() === 2) {
                $sql = 'UPDATE ' . TBL_PREFERENCES . '
                       SET prf_value = 1
                     WHERE prf_name = \'system_organization_select\'';
                $gDb->queryPrepared($sql);
            }

            $gDb->endTransaction();

            // create html page object
            $page = new HtmlPage('admidio-new-organization-successful', $gL10n->get('INS_SETUP_WAS_SUCCESSFUL'));

            $page->addHtml('<p class="lead">' . $gL10n->get('ORG_ORGANIZATION_SUCCESSFULLY_ADDED', array($_POST['orgaLongName'])) . '</p>');

            // show form
            $form = new HtmlForm('add_new_organization_form', ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences.php', $page);
            $form->addSubmitButton('btn_forward', $gL10n->get('SYS_NEXT'), array('icon' => 'bi-arrow-right-circle-fill'));

            // add form to html page and show page
            $page->addHtml($form->show());
            $page->show();

            // clean up
            unset($_SESSION['add_organization_request']);
            break;

        // set directory protection, write htaccess
        case 'htaccess':
            if (is_file(ADMIDIO_PATH . FOLDER_DATA . '/.htaccess')) {
                echo $gL10n->get('SYS_ON');
                return;
            }

            // create ".htaccess" file for folder "adm_my_files"
            $htaccess = new Htaccess(ADMIDIO_PATH . FOLDER_DATA);
            if ($htaccess->protectFolder()) {
                echo $gL10n->get('SYS_ON');
                return;
            }

            $gLogger->warning('htaccess file could not be created!');

            echo $gL10n->get('SYS_OFF');
            break;

        // send test email
        case 'test_email':
            $debugOutput = '';

            $email = new Email();
            $email->setDebugMode(true);

            if ($gSettingsManager->getBool('mail_html_registered_users')) {
                $email->setHtmlMail();
            }

            // set email data
            $email->setSender($gSettingsManager->getString('email_administrator'), $gL10n->get('SYS_ADMINISTRATOR'));
            $email->addRecipientsByUser($gCurrentUser->getValue('usr_uuid'));
            $email->setSubject($gL10n->get('SYS_EMAIL_FUNCTION_TEST', array($gCurrentOrganization->getValue('org_longname', 'database'))));
            $email->setTemplateText(
                $gL10n->get('SYS_EMAIL_FUNCTION_TEST_CONTENT', array($gCurrentOrganization->getValue('org_homepage'), $gCurrentOrganization->getValue('org_longname'))),
                $gCurrentUser->getValue('FIRSTNAME') . ' ' . $gCurrentUser->getValue('LASTNAME'),
                $gCurrentUser->getValue('EMAIL'),
                $gCurrentUser->getValue('usr_uuid'),
                $gL10n->get('SYS_ADMINISTRATOR')
            );

            // finally send the mail
            $sendResult = $email->sendEmail();

            if (isset($GLOBALS['phpmailer_output_debug'])) {
                $debugOutput .= '<br /><br /><h3>' . $gL10n->get('SYS_DEBUG_OUTPUT') . '</h3>' . $GLOBALS['phpmailer_output_debug'];
            }

            // message if send/save is OK
            if ($sendResult === true) { // don't remove check === true. ($sendResult) won't work
                $gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences.php', array('show_option' => 'email_dispatch')));
                $gMessage->show($gL10n->get('SYS_EMAIL_SEND') . $debugOutput);
                // => EXIT
            } else {
                $gMessage->show($gL10n->get('SYS_EMAIL_NOT_SEND', array($gL10n->get('SYS_RECIPIENT'), $sendResult)) . $debugOutput);
                // => EXIT
            }
            break;

        // create backup of Admidio database
        case 'backup':
            // function not available for other databases except MySQL
            if (DB_ENGINE !== Database::PDO_ENGINE_MYSQL) {
                throw new AdmException('SYS_MODULE_DISABLED');
            }

            $dump = new DatabaseDump($gDb);
            $dump->create('admidio_dump_' . $g_adm_db . '.sql.gzip');
            $dump->export();
            $dump->deleteDumpFile();
            break;
    }
} catch (AdmException|Exception $exception) {
    if ($getMode === 'save') {
        echo json_encode(array('status' => 'error', 'message' => $exception->getMessage()));
    } elseif ($getMode === 'html_form') {
        echo $exception->getMessage();
    } else {
        $gMessage->show($exception->getMessage());
    }
}
