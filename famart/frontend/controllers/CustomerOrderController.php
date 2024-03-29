<?php

namespace frontend\controllers;

use Yii;
use common\models\CustomerOrder;
use common\models\CustomerOrderSearch;
use common\models\Item;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * CustomerOrderController implements the CRUD actions for CustomerOrder model.
 */
class CustomerOrderController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
            'access' => [
                'class' => \yii\filters\AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'view', 'create', 'update', 'delete', 'pdf', 'add-order-item'],
                        'roles' => ['@']
                    ],
                    [
                        'allow' => false
                    ]
                ]
            ]
        ];
    }

    /**
     * Lists all CustomerOrder models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CustomerOrderSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single CustomerOrder model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        $providerOrderItem = new \yii\data\ArrayDataProvider([
            'allModels' => $model->orderItems,
        ]);
        return $this->render('view', [
            'model' => $this->findModel($id),
            'providerOrderItem' => $providerOrderItem,
        ]);
    }

    /**
     * Creates a new CustomerOrder model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new CustomerOrder();
        $transaction = CustomerOrder::getDb()->beginTransaction();
        try {
            if($model->loadAll(Yii::$app->request->post())) {
                if ($model->saveAll()) {
                    $post = Yii::$app->request->post();
                    foreach ($post['OrderItem'] as $value) {
                        $item = Item::findOne(['id' => $value['item_id']]);
                        $item->stock = $item->stock - $value['qty'];
                        $item->scenario = Item::SCENARIO_UPDATE;
                        if(!$item->save()) {
                            $transaction->rollBack();
                            return $this->render('create', [
                                'model' => $model,
                            ]);
                        }
                    }

                    $transaction->commit();

                    return $this->redirect(['view', 'id' => $model->id]);
                } else {
                    $transaction->rollBack();
                    return $this->render('create', [
                        'model' => $model,
                    ]);
                }
            } else {
                $transaction->rollBack();
                return $this->render('create', [
                    'model' => $model,
                ]);
            }

        } catch(\Exception $e) {
            $transaction->rollBack();
            throw $e;
        } catch(\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Updates an existing CustomerOrder model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->loadAll(Yii::$app->request->post()) && $model->saveAll()) {
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing CustomerOrder model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }
    
    /**
     * 
     * Export CustomerOrder information into PDF format.
     * @param integer $id
     * @return mixed
     */
    public function actionPdf($id) {
        $model = $this->findModel($id);
        $providerOrderItem = new \yii\data\ArrayDataProvider([
            'allModels' => $model->orderItems,
        ]);

        $content = $this->renderPartial('_pdf', [
            'model' => $model,
            'providerOrderItem' => $providerOrderItem,
        ]);

        $pdf = new \kartik\mpdf\Pdf([
            'mode' => \kartik\mpdf\Pdf::MODE_CORE,
            'format' => \kartik\mpdf\Pdf::FORMAT_A4,
            'orientation' => \kartik\mpdf\Pdf::ORIENT_PORTRAIT,
            'destination' => \kartik\mpdf\Pdf::DEST_BROWSER,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => '.kv-heading-1{font-size:18px}',
            'options' => ['title' => \Yii::$app->name],
            'methods' => [
                'SetHeader' => [\Yii::$app->name],
                'SetFooter' => ['{PAGENO}'],
            ]
        ]);

        return $pdf->render();
    }

    
    /**
     * Finds the CustomerOrder model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return CustomerOrder the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = CustomerOrder::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
    
    /**
    * Action to load a tabular form grid
    * for OrderItem
    * @author Yohanes Candrajaya <moo.tensai@gmail.com>
    * @author Jiwantoro Ndaru <jiwanndaru@gmail.com>
    *
    * @return mixed
    */
    public function actionAddOrderItem()
    {
        if (Yii::$app->request->isAjax) {
            $row = Yii::$app->request->post('OrderItem');
            if((Yii::$app->request->post('isNewRecord') && Yii::$app->request->post('_action') == 'load' && empty($row)) || Yii::$app->request->post('_action') == 'add')
                $row[] = [];
            return $this->renderAjax('_formOrderItem', ['row' => $row]);
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
