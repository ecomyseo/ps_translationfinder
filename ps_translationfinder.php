<?php
/**
 * Translation Finder
 *
 * Buscador y editor unificado de traducciones de PrestaShop (front, back office,
 * modulos, theme y emails) sin overrides.
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once _PS_MODULE_DIR_ . 'ps_translationfinder/classes/TranslationfinderLogger.php';
require_once _PS_MODULE_DIR_ . 'ps_translationfinder/classes/TranslationCatalog.php';
require_once _PS_MODULE_DIR_ . 'ps_translationfinder/classes/TranslationScanner.php';
require_once _PS_MODULE_DIR_ . 'ps_translationfinder/classes/TranslationSaver.php';
require_once _PS_MODULE_DIR_ . 'ps_translationfinder/classes/TranslationHistory.php';

class Ps_Translationfinder extends Module
{
    /** @var string Nombre del controlador admin asociado. */
    const ADMIN_CONTROLLER = 'AdminPsTranslationfinder';

    public function __construct()
    {
        $this->name = 'ps_translationfinder';
        $this->tab = 'administration';
        $this->version = '1.0.5';
        $this->author = 'Ecom Experts';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Translation Finder', [], 'Modules.Pstranslationfinder.Admin');
        $this->description = $this->trans('Busca cualquier texto de traduccion (front, back, modulos, theme, emails) y cambialo desde un solo buscador.', [], 'Modules.Pstranslationfinder.Admin');
        $this->confirmUninstall = $this->trans('Seguro que quieres desinstalar el modulo? Las traducciones guardadas en base de datos se mantendran.', [], 'Modules.Pstranslationfinder.Admin');
    }

    /**
     * El modulo usa el sistema de traduccion moderno (Symfony).
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * Instalacion: crea la pestana del back office.
     */
    public function install()
    {
        Configuration::updateValue('PS_TFINDER_DEBUG', 0);
        Configuration::updateValue('PS_TFINDER_CONTENT', 0);
        Configuration::updateValue('PS_TFINDER_BACKUP', 1);

        return parent::install()
            && $this->installTab();
    }

    public function uninstall()
    {
        Configuration::deleteByName('PS_TFINDER_DEBUG');
        Configuration::deleteByName('PS_TFINDER_CONTENT');
        Configuration::deleteByName('PS_TFINDER_BACKUP');

        return $this->uninstallTab()
            && parent::uninstall();
    }

    /**
     * Crea la pestana (Tab) que da acceso al controlador del buscador.
     */
    protected function installTab()
    {
        if (Tab::getIdFromClassName(self::ADMIN_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = self::ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentLocalization');
        if (!$tab->id_parent) {
            $tab->id_parent = (int) Tab::getIdFromClassName('AdminAdmin');
        }
        $tab->icon = 'translate';
        $tab->active = 1;

        $names = [];
        foreach (Language::getLanguages(false) as $lang) {
            $names[(int) $lang['id_lang']] = 'Buscar traduccion';
        }
        $tab->name = $names;

        try {
            return (bool) $tab->add();
        } catch (Exception $e) {
            TranslationfinderLogger::log('installTab error: ' . $e->getMessage());

            return false;
        }
    }

    protected function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        if (!$id_tab) {
            return true;
        }
        try {
            $tab = new Tab($id_tab);

            return (bool) $tab->delete();
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * Anade nuevas funciones / repara la instalacion al entrar en la configuracion.
     * Envuelto en try para no romper el back office.
     */
    public function addnewfeatures()
    {
        try {
            if (!Tab::getIdFromClassName(self::ADMIN_CONTROLLER)) {
                $this->installTab();
            }
        } catch (Exception $e) {
            TranslationfinderLogger::log('addnewfeatures error: ' . $e->getMessage());
        }
    }

    /**
     * Pagina de configuracion: guarda ajustes y redirige al buscador.
     */
    public function getContent()
    {
        $this->addnewfeatures();

        $output = '';

        if (Tools::isSubmit('submitPsTranslationfinder')) {
            Configuration::updateValue('PS_TFINDER_DEBUG', (int) Tools::getValue('PS_TFINDER_DEBUG') ? 1 : 0);
            Configuration::updateValue('PS_TFINDER_CONTENT', (int) Tools::getValue('PS_TFINDER_CONTENT') ? 1 : 0);
            Configuration::updateValue('PS_TFINDER_BACKUP', (int) Tools::getValue('PS_TFINDER_BACKUP') ? 1 : 0);
            $output .= $this->displayConfirmation($this->trans('Configuracion guardada.', [], 'Modules.Pstranslationfinder.Admin'));
        }

        $admin_link = $this->context->link->getAdminLink(self::ADMIN_CONTROLLER);

        $this->context->smarty->assign([
            'ps_tf_admin_link' => $admin_link,
            'ps_tf_debug' => (int) Configuration::get('PS_TFINDER_DEBUG'),
            'ps_tf_content' => (int) Configuration::get('PS_TFINDER_CONTENT'),
            'ps_tf_backup' => (Configuration::get('PS_TFINDER_BACKUP') === false)
                ? 1
                : (int) Configuration::get('PS_TFINDER_BACKUP'),
        ]);

        $output .= $this->display(__FILE__, 'views/templates/admin/configure.tpl');

        return $output;
    }
}
