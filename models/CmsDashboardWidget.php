<?php

namespace skeeks\cms\models;

use skeeks\cms\base\Widget;
use skeeks\cms\models\behaviors\Serialize;
use skeeks\cms\modules\admin\base\AdminDashboardWidget;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%cms_dashboard_widget}}".
 *
 * @property integer $id
 * @property integer $created_by
 * @property integer $updated_by
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $cms_dashboard_id
 * @property integer $priority
 * @property string $component
 * @property string $component_settings
 * @property string $cms_dashboard_column
 *
 *
 * @property string $name
 *
 * @property CmsDashboard $cmsDashboard
 *
 * @property AdminDashboardWidget $widget
 */
class CmsDashboardWidget extends \skeeks\cms\models\Core
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%cms_dashboard_widget}}';
    }

    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            Serialize::className() =>
            [
                'class' => Serialize::className(),
                'fields' => ['component_settings']
            ]
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_by', 'updated_by', 'created_at', 'updated_at', 'cms_dashboard_id', 'priority'], 'integer'],
            [['cms_dashboard_id', 'component'], 'required'],
            [['component_settings'], 'safe'],
            [['component'], 'string', 'max' => 255],

            [['cms_dashboard_column'], 'integer', 'max' => 6, 'min' => 1],
            [['cms_dashboard_column'], 'default', 'value' => 1],
            [['priority'], 'default', 'value' => 50],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'created_by' => Yii::t('app', 'Created By'),
            'updated_by' => Yii::t('app', 'Updated By'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'cms_dashboard_id' => Yii::t('app', 'Cms Dashboard ID'),
            'priority' => Yii::t('app', 'Priority'),
            'component' => Yii::t('app', 'Component'),
            'component_settings' => Yii::t('app', 'Component Settings'),
            'cms_dashboard_column' => Yii::t('app', 'cms_dashboard_column'),
        ];
    }



    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCmsDashboard()
    {
        return $this->hasOne(CmsDashboard::className(), ['id' => 'cms_dashboard_id']);
    }



    /**
     * @return AdminDashboardWidget
     * @throws \yii\base\InvalidConfigException
     */
    public function getWidget()
    {
        if ($this->component)
        {
            if (class_exists($this->component))
            {
                /**
                 * @var $component AdminDashboardWidget
                 */
                $component = \Yii::createObject($this->component);
                $component->load($this->component_settings, "");

                return $component;
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public function getName()
    {
        if ($this->widget)
        {
            if ($this->widget->getAttributes(['name']))
            {
                return (string) $this->widget->name;
            }
        }

        return '';
    }
}