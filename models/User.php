<?php
/**
 * User
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010-2014 SkeekS (Sx)
 * @date 20.10.2014
 * @since 1.0.0
 */

namespace skeeks\cms\models;

use Imagine\Image\ManipulatorInterface;
use skeeks\cms\authclient\models\UserAuthClient;
use skeeks\cms\components\Cms;
use skeeks\cms\models\behaviors\HasRelatedProperties;
use skeeks\cms\models\behaviors\HasStorageFile;
use skeeks\cms\models\behaviors\traits\HasRelatedPropertiesTrait;
use skeeks\cms\models\user\UserEmail;
use skeeks\cms\Module;
use skeeks\cms\validators\PhoneValidator;
use Yii;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;
use yii\validators\EmailValidator;
use yii\validators\UniqueValidator;
use yii\web\IdentityInterface;

use skeeks\cms\models\behaviors\HasSubscribes;

/**
 * This is the model class for table "{{%cms_user}}".
 *
 * @property integer $id
 * @property string $username
 * @property string $auth_key
 * @property string $password_hash
 * @property string $password_reset_token
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $name
 * @property integer $image_id
 *
 * @property string $gender
 * @property string $active
 * @property integer $updated_by
 * @property integer $created_by
 * @property integer $logged_at
 * @property integer $last_activity_at
 * @property integer $last_admin_activity_at
 * @property string  $email
 * @property string  $phone
 * @property integer $email_is_approved
 * @property integer $phone_is_approved
 *
 * @property string $lastActivityAgo
 * @property string $lastAdminActivityAgo
 *
 * @property CmsStorageFile $image
 * @property string $avatarSrc
 * @property string $profileUrl


 *
 * @property CmsUserEmail[]     $cmsUserEmails
 * @property CmsUserPhone[]     $cmsUserPhones
 * @property UserAuthClient[]   $cmsUserAuthClients
 *
 * @property \yii\rbac\Role[]   $roles
 * @property []   $roleNames
 *
 * @property string $displayName
 * @property string $profileUrl
 *
 * @property CmsContentElement2cmsUser[] $cmsContentElement2cmsUsers
 * @property CmsContentElement[] $favoriteCmsContentElements
 *
 */
class User
    extends Core
    implements IdentityInterface
{
    use HasRelatedPropertiesTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%cms_user}}';
    }

    /**
     * Логины которые нельзя удалять, и нельзя менять
     * @return array
     */
    static public function getProtectedUsernames()
    {
        return ['root', 'admin'];
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->on(self::EVENT_AFTER_INSERT,     [$this, "_cmsAfterSave"]);
        $this->on(self::EVENT_AFTER_UPDATE,     [$this, "_cmsAfterSave"]);

        $this->on(self::EVENT_BEFORE_DELETE,    [$this, "checkDataBeforeDelete"]);
    }

    public function _cmsAfterSave($e)
    {
        if ($this->_roleNames !== null)
        {
            if ($this->roles)
            {
                foreach ($this->roles as $roleExist)
                {
                    if (!in_array($roleExist->name, (array) $this->_roleNames))
                    {
                        \Yii::$app->authManager->revoke($roleExist, $this->id);
                    }
                }
            }

            foreach ((array) $this->_roleNames as $roleName)
            {
                if ($role = \Yii::$app->authManager->getRole($roleName))
                {
                    try
                    {
                        \Yii::$app->authManager->assign($role, $this->id);
                    } catch(\Exception $e)
                    {}
                }
            }
        }
    }
    /**
     * @throws Exception
     */
    public function checkDataBeforeDelete($e)
    {
        if (in_array($this->username, static::getProtectedUsernames()))
        {
            throw new Exception(\Yii::t('app','This user can not be removed'));
        }

        if ($this->id == \Yii::$app->user->identity->id)
        {
            throw new Exception(\Yii::t('app','You can not delete yourself'));
        }
    }


    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [

            TimestampBehavior::className(),

            HasStorageFile::className() =>
            [
                'class'     => HasStorageFile::className(),
                'fields'    => ['image_id']
            ],

            HasRelatedProperties::className() =>
            [
                'class'                             => HasRelatedProperties::className(),
                'relatedElementPropertyClassName'   => CmsUserProperty::className(),
                'relatedPropertyClassName'          => CmsUserUniversalProperty::className(),
            ],

        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['active', 'default', 'value' => Cms::BOOL_Y],
            ['gender', 'default', 'value' => 'men'],
            ['gender', 'in', 'range' => ['men', 'women']],

            [['created_at', 'updated_at', 'image_id', 'email_is_approved', 'phone_is_approved'], 'integer'],

            [['gender'], 'string'],
            [['username', 'password_hash', 'password_reset_token', 'email', 'name'], 'string', 'max' => 255],
            [['auth_key'], 'string', 'max' => 32],

            [['phone'], 'string', 'max' => 64],
            [['phone'], PhoneValidator::className()],
            [['phone'], 'unique'],
            [['phone', 'email'], 'default', 'value' => null],


            [['email'], 'unique'],
            [['email'], 'email'],

            //[['username'], 'required'],
            ['username', 'string', 'min' => 3, 'max' => 25],
            [['username'], 'unique'],
            [['username'], \skeeks\cms\validators\LoginValidator::className()],

            [['logged_at'], 'integer'],
            [['last_activity_at'], 'integer'],
            [['last_admin_activity_at'], 'integer'],

            [['username'], 'default', 'value' => function(self $model)
            {
                $userLast = static::find()->orderBy("id DESC")->one();
                return "id" . ($userLast->id + 1);
            }],

            [['email_is_approved', 'phone_is_approved'], 'default', 'value' => 0],

            [['auth_key'], 'default', 'value' => function(self $model)
            {
                return \Yii::$app->security->generateRandomString();
            }],

            [['password_hash'], 'default', 'value' => function(self $model)
            {
                return \Yii::$app->security->generatePasswordHash(\Yii::$app->security->generateRandomString());
            }],

            [['roleNames'], 'safe'],
            [['roleNames'], 'default', 'value' => \Yii::$app->cms->registerRoles]
        ];
    }

    public function extraFields()
    {
        return [
            'displayName',
        ];
    }
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'username' => Yii::t('app', 'Login'),
            'auth_key' => Yii::t('app', 'Auth Key'),
            'password_hash' => Yii::t('app', 'Password Hash'),
            'password_reset_token' => Yii::t('app', 'Password Reset Token'),
            'email' => Yii::t('app', 'Email'),
            'phone' => Yii::t('app', 'Phone'),
            'active' => Yii::t('app', 'Active'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'name' => \Yii::t('skeeks/cms/user', 'Name'), //Yii::t('skeeks/cms', 'Name???'),
            'gender' => Yii::t('app', 'Gender'),
            'logged_at' => Yii::t('app', 'Logged At'),
            'last_activity_at' => Yii::t('app', 'Last Activity At'),
            'last_admin_activity_at' => Yii::t('app', 'Last Activity In The Admin At'),
            'image_id' => Yii::t('app', 'Image'),
            'roleNames' => Yii::t('app', 'Группы'),
            'email_is_approved' => Yii::t('app', 'Email is approved'),
            'phone_is_approved' => Yii::t('app', 'Phone is approved'),
        ];
    }


    /**
     * Установка последней активности пользователя. Больше чем в настройках.
     * @return $this
     */
    public function lockAdmin()
    {
        $this->last_admin_activity_at   = \Yii::$app->formatter->asTimestamp(time()) - (\Yii::$app->admin->blockedTime + 1);
        $this->save(false);

        return $this;
    }

    /**
     * Время проявления последней активности на сайте
     *
     * @return int
     */
    public function getLastAdminActivityAgo()
    {
        $now = \Yii::$app->formatter->asTimestamp(time());
        return (int) ($now - (int) $this->last_admin_activity_at);
    }
    /**
     * Обновление времени последней актиности пользователя.
     * Только в том случае, если время его последней актиности больше 10 сек.
     * @return $this
     */
    public function updateLastAdminActivity()
    {
        $now = \Yii::$app->formatter->asTimestamp(time());

        if (!$this->lastAdminActivityAgo || $this->lastAdminActivityAgo > 10)
        {
            $this->last_activity_at         = $now;
            $this->last_admin_activity_at   = $now;

            $this->save(false);
        }

        return $this;
    }


    /**
     * Время проявления последней активности на сайте
     *
     * @return int
     */
    public function getLastActivityAgo()
    {
        $now = \Yii::$app->formatter->asTimestamp(time());
        return (int) ($now - (int) $this->last_activity_at);
    }
    /**
     * Обновление времени последней актиности пользователя.
     * Только в том случае, если время его последней актиности больше 10 сек.
     * @return $this
     */
    public function updateLastActivity()
    {
        $now = \Yii::$app->formatter->asTimestamp(time());

        if (!$this->lastActivityAgo || $this->lastActivityAgo > 10)
        {
            $this->last_activity_at = $now;
            $this->save(false);
        }

        return $this;
    }



    /**
     * @return \yii\db\ActiveQuery
     */
    public function getImage()
    {
        return $this->hasOne(StorageFile::className(), ['id' => 'image_id']);
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStorageFiles()
    {
        return $this->hasMany(StorageFile::className(), ['created_by' => 'id']);
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserAuthClients()
    {
        return $this->hasMany(UserAuthClient::className(), ['user_id' => 'id']);
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return $this->name ? $this->name : $this->username;
    }


    /**
     *
     * TODO: Is depricated > 2.7.1
     *
     * @param string $action
     * @param array $params
     * @return string
     */
    public function getPageUrl($action = 'view', $params = [])
    {
        return $this->getProfileUrl($action, $params);
    }


    /**
     * @param string $action
     * @param array $params
     * @return string
     */
    public function getProfileUrl($action = 'view', $params = [])
    {
        $params = ArrayHelper::merge([
            "cms/user/" . $action,
            "username" => $this->username
        ], $params);

        return \Yii::$app->urlManager->createUrl($params);
    }





    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'active' => Cms::BOOL_Y]);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException(\Yii::t('app','"findIdentityByAccessToken" is not implemented.'));
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'active' => Cms::BOOL_Y]);
    }

    /**
     * Finds user by email
     *
     * @param $email
     * @return static
     */
    public static function findByEmail($email)
    {
        return static::findOne(['email' => $email, 'active' => Cms::BOOL_Y]);
    }

    /**
     * @param $phone
     * @return null|CmsUser
     */
    public static function findByPhone($phone)
    {
        return static::findOne(['phone' => $phone, 'active' => Cms::BOOL_Y]);

        return null;
    }


    /**
     * Поиск пользователя по email или логину
     * @param $value
     * @return User
     */
    static public function findByUsernameOrEmail($value)
    {
        if ($user = static::findByUsername($value))
        {
            return $user;
        }

        if ($user = static::findByEmail($value))
        {
            return $user;
        }

        return null;
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token))
        {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'active' => Cms::BOOL_Y,
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return boolean
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token))
        {
            return false;
        }
        $expire = Yii::$app->cms->passwordResetTokenExpire;
        $parts = explode('_', $token);
        $timestamp = (int) end($parts);
        return $timestamp + $expire >= time();
    }


    /**
     * Заполнить модель недостающими данными, которые необходимы для сохранения пользователя
     * @return $this
     */
    public function populate()
    {
        $password               = \Yii::$app->security->generateRandomString(6);

        $this->generateUsername();
        $this->setPassword($password);
        $this->generateAuthKey();

        return $this;
    }
    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return boolean if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Генерация логина пользователя
     * @return $this
     */
    public function generateUsername()
    {
        /*if ($this->email)
        {
            $userName = \skeeks\cms\helpers\StringHelper::substr($this->email, 0, strpos() );
        }*/

        $userLast = static::find()->orderBy("id DESC")->one();
        $this->username = "id" . ($userLast->id + 1);

        if (static::find()->where(['username' => $this->username])->one())
        {
            $this->username = $this->username . "_" . \skeeks\cms\helpers\StringHelper::substr( md5(time()), 0, 6);
        }

        return $this;
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }

    /**
     * @param int $width
     * @param int $height
     * @param $mode
     * @return mixed|null|string
     */
    public function getAvatarSrc($width = 50, $height = 50, $mode = ManipulatorInterface::THUMBNAIL_OUTBOUND)
    {
        if ($this->image)
        {
            return \Yii::$app->imaging->getImagingUrl($this->image->src, new \skeeks\cms\components\imaging\filters\Thumbnail([
                'w'    => $width,
                'h'    => $height,
                'm'    => $mode,
            ]));
        }
    }



    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCmsUserAuthClients()
    {
        return $this->hasMany(UserAuthClient::className(), ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCmsUserEmails()
    {
        return $this->hasMany(CmsUserEmail::className(), ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCmsUserPhones()
    {
        return $this->hasMany(CmsUserPhone::className(), ['user_id' => 'id']);
    }

    /**
     * @return \yii\rbac\Role[]
     */
    public function getRoles()
    {
        return \Yii::$app->authManager->getRolesByUser($this->id);
    }




    protected $_roleNames = null;

    /**
     * @return array
     */
    public function getRoleNames()
    {
        if ($this->_roleNames !== null)
        {
            return $this->_roleNames;
        }

        $this->_roleNames = (array) ArrayHelper::map($this->roles, 'name', 'name');
        return $this->_roleNames;
    }

    /**
     * @param array $roleNames
     * @return $this
     */
    public function setRoleNames($roleNames = [])
    {
        $this->_roleNames = $roleNames;

        return $this;
    }





    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCmsContentElement2cmsUsers()
    {
        return $this->hasMany(CmsContentElement2cmsUser::className(), ['cms_user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFavoriteCmsContentElements()
    {
        return $this->hasMany(CmsContentElement::className(), ['id' => 'cms_content_element_id'])
                    ->via('cmsContentElement2cmsUsers');
    }

}
