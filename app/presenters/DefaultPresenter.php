<?php

/**
 * My NApplication
 *
 * @copyright  Copyright (c) 2010 John Doe
 * @package    MyApplication
 */

/**
 * Homepage presenter.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class DefaultPresenter extends BasePresenter {

    public function actionImport() {
        $this['header']->addTitle('Import');
    }

    public function actionShowLog() {

    }

    protected function createComponentLogGrid($name) {
        $grid = new Grid($this, $name);

        $grid->model = new DibiFluentModel(dibi::select("*")->from("rozvrh_main.log")->orderBy(array("timestamp" => 'DESC')));
        $grid->setItemsPerPage(5);

        $grid->addColumn("log_id", "ID#");
        $grid->addColumn('component', 'Komponenta');
        $grid->addColumn("severity", "Závažnost", function ($record) {
                    $m = $record->severity;
                    echo '<span class="' . $m . '">';
                    switch ($m) {
                        case Logger::CRITICAL :
                            echo 'Kritická';
                            break;
                        case Logger::WARNING :
                            echo 'Varování';
                            break;
                        case Logger::NOTICE :
                            echo 'Poznámka';
                            break;
                        case Logger::DEBUG :
                            echo 'Ladící';
                            break;
                        default:
                            echo 'Info';
                            break;
                    }
                    echo "</span>";
                });
        $grid->addColumn("message", "Zpráva");
        $grid->addColumn("timestamp", "Čas", function ($record) {
                    echo date("d.n.Y H:i:s", strtotime($record->timestamp));
                });
        $grid->setItemsPerPage(50);
        return $grid;
    }

    public function actionDownload() {
        $this['header']->addTitle('Stažení XML');
    }

    public function createComponentDownloadForm() {
        $form = new NAppForm($this, 'downloadForm');
        $form->addText('url', 'URL souboru')
                        ->setType('url')
                        ->setRequired('URL musí být vyplněno.')
                        ->getControlPrototype()->class[] = 'long';
        $form->addText('login', 'Fakultní login');
        $form->addPassword('password', 'Heslo');

        $form->addCheckbox('check', 'Zkontrolovat nejdříve jestli je k dispozici novější verze.')->setDefaultValue(TRUE);

        $form->addSubmit('download', 'Stáhnout')->onClick[] = callback($this, 'downloadFile');

        $config = NEnvironment::getConfig('xml');

        $form->setDefaults(array(
            'url' => $config['remoteRepository']
        ));
        return $form;
    }

    public function downloadFile(NSubmitButton $button) {

        $values = $button->getForm()->getValues();
        try {
            $downloader = $this['downloader'];
            $downloader->setUrl($values['url'])
                    ->setLogin($values['login'])
                    ->setPassword($values['password'])
                    ->setLocalRepository(NEnvironment::getConfig('xml')->localRepository);

            if ($values['check'] == TRUE) {
                if ($downloader->checkForNewer() == IDownloader::NOT_MODIFIED) {
                    $this->flashMessage('V úložišti není k dispozici žádný novější soubor.');
                    return;
                }
            }

            $res = $downloader->download();
            $this->flashMessage('Soubor stažen (' . $res['file'] . ', velikost:' . NTemplateHelpers::bytes($res['size']) . ', celkový čas:' . round($res['time'], 2) . ' sec)', 'success');
            $this->redirect('this');
        } catch (IOException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    protected function createComponentDownloader() {
        return $this->getApplication()->getContext()->getService('IDownloader');
    }

    protected function createComponentImporter() {
        return $this->getApplication()->getContext()->getService('IImporter');
    }

}
