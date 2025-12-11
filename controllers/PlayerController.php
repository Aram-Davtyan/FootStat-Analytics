<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\components\services\APISofascoreServices;

class PlayerController extends Controller
{
    protected APISofascoreServices $sofascoreService;

    public function __construct($id, $module, $config = [], ?APISofascoreServices $sofascoreService = null)
    {
        $this->sofascoreService = $sofascoreService ?? new APISofascoreServices();
        parent::__construct($id, $module, $config);
    }

    public function actionIndex()
    {
        $request = Yii::$app->request;
        $query = trim((string)$request->get('q', ''));
        $country = trim((string)$request->get('country', ''));
        $position = trim((string)$request->get('position', ''));
        $teamId = (int)$request->get('teamId', 0);
        $limit = (int)$request->get('limit', 20) ?: 20;

        $players = [];
        $positions = [];
        $countries = [];
        $error = null;

        if ($query !== '') {
            try {
                $result = $this->sofascoreService->getPlayersWithFilters(
                    $query,
                    $country,
                    $position,
                    $teamId > 0 ? $teamId : null,
                    $limit
                );
                $players = $result['players'];
                $positions = $result['positions'];
                $countries = $result['countries'];
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('index', [
            'players' => $players,
            'error' => $error,
            'query' => $query,
            'country' => $country,
            'position' => $position,
            'positions' => $positions,
            'countries' => $countries,
            'teamId' => $teamId,
            'limit' => $limit,
        ]);
    }
}
