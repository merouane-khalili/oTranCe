<?php
/**
 * This file is part of oTranCe http://www.oTranCe.de
 *
 * @package         oTranCe
 * @subpackage      Controllers
 * @version         SVN: $Rev$
 * @author          $Author$
 */
/**
 * Index Controller
 *
 * @package         oTranCe
 * @subpackage      Controllers
 */
class IndexController extends OtranceController
{
    /**
     * Remember last controler
     *
     * @var string
     */
    private $_lastController;

    /**
     * Remember last action
     *
     * @var string
     */
    private $_lastAction;

    /**
     * Init
     *
     * @return void
     */
    public function init()
    {
        $this->_lastController = $this->_request->getParam('lastController', 'index');
        $this->_lastAction     = $this->_request->getParam('lastAction', 'index');
    }

    /**
     * Process index action
     *
     * @return void
     */
    public function indexAction()
    {
        $languagesModel = new Application_Model_Languages();
        $entriesModel   = new Application_Model_LanguageEntries();

        $languages = $languagesModel->getAllLanguages();
        $this->view->assign(
            array(
                 'user'        => $this->_userModel,
                 'languages'   => $languages,
                 'translators' => $this->_userModel->getTranslatorList(false, true),
                 'status'      => $entriesModel->getStatus($languages)
            )
        );
    }

    /**
     * Redirect to url
     *
     * @param array $url Target Url
     *
     * @return void
     */
    private function _doRedirect(array $url = array())
    {
        $this->_response->setRedirect($this->view->url($url, null, true));
    }

    /**
     * Logout the user and redirect him to login page
     *
     * @return void
     */
    public function logoutAction()
    {
        $historyModel = new Application_Model_History();
        $historyModel->logLogout();
        //un-Auth user
        $user = new Msd_User();
        $user->logout();
        setcookie('oTranCe_autologin', null, null, '/');
        $this->_doRedirect(
            array(
                 'controller' => 'index',
                 'action'     => 'login',
            )
        );
    }

    /**
     * User login
     *
     * @return void
     */
    public function loginAction()
    {
        $form = new Application_Form_Login();
        if ($this->_request->isPost() && $this->_request->getParam('switchLanguage', null) === null) {
            $loginResult  = false;
            $historyModel = new Application_Model_History();
            $user         = new Msd_User();
            $postData     = $this->_request->getParams();
            if ($form->isValid($postData)) {
                $autoLogin   = ($postData['autologin'] == 1) ? true : false;
                $loginResult = $user->login(
                    $postData['user'],
                    $postData['pass'],
                    $autoLogin
                );
                $message = $user->getAuthMessages();
                if ($loginResult === Msd_User::SUCCESS) {
                    $historyModel->logLoginSuccess();

                    // load user setting for the interface language
                    $this->_userModel   = new Application_Model_User();
                    $guiLanguage = $this->_userModel->loadSetting('interfaceLanguage', null);
                    if ($guiLanguage !== null) {
                        $this->view->dynamicConfig->setParam('interfaceLanguage', $guiLanguage);
                    }

                    $this->_redirectAfterSuccessfulLogin();

                    return;
                } else {
                    if ($loginResult == Msd_User::NOT_ACTIVE) {
                        $message = 'L_LOGIN_ACCOUNT_NOT_ACTIVE';
                    }
                    $loginResult = false;
                }
            }

            if ($loginResult === false) {
                $historyModel->logLoginFailed($postData['user']);
                $this->view->popUpMessage()->addMessage(
                    'login-message',
                    'L_LOGIN',
                    $message, //'L_LOGIN_INVALID_USER',
                    array(
                         'modal'       => true,
                         'dialogClass' => 'error'
                    )
                );
            }
        }
        $this->view->assign(
            array(
                 'isLogin'               => true,
                 'form'                  => $form,
                 'availableGuiLanguages' => $this->view->dynamicConfig->getParam('availableGuiLanguages'),
                 'request'               => $this->_request
            )
        );
    }

    /**
     * Forward to formerly requested target page.
     * Detect if another page was called before the login was triggered and forward to this target page.
     * Otherwise forward to the index page.
     *
     * @return void
     */
    public function _redirectAfterSuccessfulLogin()
    {
        $redirectUrl = $this->view->serverUrl();
        $ns          = new Zend_Session_Namespace('requestData');
        if (!empty($ns->redirectUrl)) {
            $redirectUrl .= $ns->redirectUrl;
            $ns->redirectUrl = null;
        } else {
            $redirectUrl .= $this->view->baseUrl() . '/index/index';
        }

        $this->redirect($redirectUrl, array('exit' => true));
    }
}
