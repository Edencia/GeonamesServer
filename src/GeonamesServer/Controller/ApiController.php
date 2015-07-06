<?php
namespace GeonamesServer\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

class ApiController extends AbstractActionController
{
    /**
     * Search
     * @route /geonames/_search/:query[/:page][/:size]
     */
    public function searchAction()
    {
        $results = array();
        $serviceLocator = $this->getServiceLocator();
        $request = $this->getRequest();
        $params = $request->getQuery()->toArray();

        if (empty($params) || (empty($params['Geonames']['name']) && empty($params['Geonames']['id'])))
            return $this->redirect()->toRoute('project/view');
        $elasticsearch = $this->getServiceLocator()->get('GeonamesServer\Service\Elasticsearch');

        if (!empty($params['Geonames']['name'])) {
            $query = $params['Geonames']['name'];
            $page  = $this->params()->fromRoute('page', 1);
            $size  = $this->params()->fromRoute('size', 10);

            $datas = $elasticsearch->search($query, $page, $size);
            if ($datas['success'])
                $elements = $datas['response']['hits'];
        } else {
            $datas = $elasticsearch->getDocuments($params['Geonames']['id']);
            if ($datas['success'])
                $elements = $datas['response'];
        }

        if ($datas['success']) {
            foreach ($elements as $k => $v) {
                $results[] = array(
                    'id' => $v['geonameid'],
                    'type' => 'zone',
                    'name' => $this->formatName($v),
                    'validated' => true
                );
            }
        }

        return new JsonModel(array(
            'results' => $results
        ));
    }

    public function formatName($response) {
        if (!isset($response['parents']) || empty($response['parents']))
            return $response['name'];
        $result = '';
        $result .= $response['name'] .', ';
        if (isset($response['parents'])) {
            $parents = $response['parents'];
            foreach ($parents as $parent) {
                // if ($parent['type'] == 'country')
                    $result .= $parent['name'] .', ';
            }
        }
        $result = substr($result, 0, -2);
        return $result;
    }

    /**
     * Return json documents with geonameid(s)
     * @route /geonames/_get/{geonameid},{geonameid},..
     */
    public function getAction()
    {
        $elasticsearch = $this->getServiceLocator()->get('GeonamesServer\Service\Elasticsearch');
        $geonamesids = $this->params()->fromRoute('geonameids');
        return new JsonModel($elasticsearch->getDocuments($geonamesids));
    }
}
