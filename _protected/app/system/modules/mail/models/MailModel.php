<?php
/**
 * @author         Pierre-Henry Soria <hello@ph7cms.com>
 * @copyright      (c) 2012-2017, Pierre-Henry Soria. All Rights Reserved.
 * @license        GNU General Public License; See PH7.LICENSE.txt and PH7.COPYRIGHT.txt in the root directory.
 * @package        PH7 / App / System / Module / Mail / Model
 */

namespace PH7;

use PH7\Framework\Mvc\Model\Spam as SpamModel;
use PH7\Framework\Mvc\Model\Engine\Db;
use PH7\Framework\Error\CException\PH7InvalidArgumentException;

class MailModel extends MailCoreModel
{
    const INBOX = 1, OUTBOX = 2, TRASH = 3;

    const TRASH_MODE = 'trash';
    const RESTOR_MODE = 'restor';
    const DELETE_MODE = 'delete';

    const MODES = [
        self::TRASH_MODE,
        self::RESTOR_MODE,
        self::DELETE_MODE
    ];

    /**
     * @param integer $iRecipient
     * @param integer $iMessageId
     *
     * @return \stdClass
     */
    public function readMsg($iRecipient, $iMessageId)
    {
        $rStmt = Db::getInstance()->prepare(
            'SELECT msg.*, m.profileId, m.username, m.firstName FROM' . Db::prefix('Messages') .
            'AS msg LEFT JOIN ' . Db::prefix('Members') . 'AS m ON msg.sender = m.profileId
            WHERE msg.recipient = :recipient AND msg.messageId = :messageId AND NOT FIND_IN_SET(\'recipient\', msg.trash) LIMIT 1'
        );

        $rStmt->bindValue(':recipient', $iRecipient, \PDO::PARAM_INT);
        $rStmt->bindValue(':messageId', $iMessageId, \PDO::PARAM_INT);
        $rStmt->execute();
        return $rStmt->fetch(\PDO::FETCH_OBJ);
    }

    /**
     * @param integer $iSender
     * @param integer $iMessageId
     *
     * @return \stdClass
     */
    public function readSentMsg($iSender, $iMessageId)
    {
        $rStmt = Db::getInstance()->prepare(
            'SELECT msg.*, m.profileId, m.username, m.firstName FROM' . Db::prefix('Messages') .
            'AS msg LEFT JOIN ' . Db::prefix('Members') . 'AS m ON msg.recipient = m.profileId
            WHERE msg.sender = :sender AND msg.messageId = :messageId AND NOT FIND_IN_SET(\'sender\', msg.toDelete) LIMIT 1'
        );

        $rStmt->bindValue(':sender', $iSender, \PDO::PARAM_INT);
        $rStmt->bindValue(':messageId', $iMessageId, \PDO::PARAM_INT);
        $rStmt->execute();
        return $rStmt->fetch(\PDO::FETCH_OBJ);
    }

    /**
     * @param integer $iProfileId
     * @param integer $iMessageId
     *
     * @return \stdClass
     */
    public function readTrashMsg($iProfileId, $iMessageId)
    {
        $rStmt = Db::getInstance()->prepare(
            'SELECT msg.*, m.profileId, m.username, m.firstName FROM' . Db::prefix('Messages') .
            'AS msg LEFT JOIN ' . Db::prefix('Members') . 'AS m ON msg.sender = m.profileId
            WHERE msg.recipient = :profileId AND msg.messageId = :messageId AND FIND_IN_SET(\'recipient\', msg.trash)
            AND NOT FIND_IN_SET(\'recipient\', msg.toDelete) LIMIT 1'
        );

        $rStmt->bindValue(':profileId', $iProfileId, \PDO::PARAM_INT);
        $rStmt->bindValue(':messageId', $iMessageId, \PDO::PARAM_INT);
        $rStmt->execute();
        return $rStmt->fetch(\PDO::FETCH_OBJ);
    }

    /**
     * Send a message.
     *
     * @param integer $iSender
     * @param integer $iRecipient
     * @param string $sTitle
     * @param string $sMessage
     * @param string $sCreatedDate
     *
     * @return boolean|integer Returns the ID of the message on success or FALSE on failure.
     */
    public function sendMsg($iSender, $iRecipient, $sTitle, $sMessage, $sCreatedDate)
    {
        $rStmt = Db::getInstance()->prepare(
            'INSERT INTO' . Db::prefix('Messages') . '(sender, recipient, title, message, sendDate, status)
            VALUES (:sender, :recipient, :title, :message, :sendDate, \'1\')'
        );
        $rStmt->bindValue(':sender', $iSender, \PDO::PARAM_INT);
        $rStmt->bindValue(':recipient', $iRecipient, \PDO::PARAM_INT);
        $rStmt->bindValue(':title', $sTitle, \PDO::PARAM_STR);
        $rStmt->bindValue(':message', $sMessage, \PDO::PARAM_STR);
        $rStmt->bindValue(':sendDate', $sCreatedDate, \PDO::PARAM_STR);
        return (!$rStmt->execute()) ? false : Db::getInstance()->lastInsertId();
    }

    /**
     * @param integer $iRecipient
     * @param integer $iMessageId
     *
     * @return boolean
     */
    public function deleteMsg($iRecipient, $iMessageId)
    {
        $rStmt = Db::getInstance()->prepare('DELETE FROM' . Db::prefix('Messages') . 'WHERE recipient = :recipient AND messageId = :messageId LIMIT 1');
        $rStmt->bindValue(':recipient', $iRecipient, \PDO::PARAM_INT);
        $rStmt->bindValue(':messageId', $iMessageId, \PDO::PARAM_INT);

        return $rStmt->execute();
    }

    /**
     * @param integer $iMessageId
     *
     * @return boolean
     */
    public function adminDeleteMsg($iMessageId)
    {
        $rStmt = Db::getInstance()->prepare('DELETE FROM' . Db::prefix('Messages') . 'WHERE messageId = :messageId LIMIT 1');
        $rStmt->bindValue(':messageId', $iMessageId, \PDO::PARAM_INT);

        return $rStmt->execute();
    }

    /**
     * @param integer $iMessageId
     */
    public function setReadMsg($iMessageId)
    {
        $rStmt = Db::getInstance()->prepare('UPDATE' . Db::prefix('Messages') . 'SET status = 0 WHERE messageId = :messageId LIMIT 1');
        $rStmt->bindValue(':messageId', $iMessageId, \PDO::PARAM_INT);
        $rStmt->execute();
        Db::free($rStmt);
    }

    /**
     * @param integer $iMessageId
     *
     * @return \stdClass
     */
    public function getMsg($iMessageId)
    {
        $rStmt = Db::getInstance()->prepare('SELECT * FROM' . Db::prefix('Messages') . 'WHERE messageId = :messageId LIMIT 1');
        $rStmt->bindValue(':messageId', $iMessageId, \PDO::PARAM_INT);
        $rStmt->execute();
        return $rStmt->fetch(\PDO::FETCH_OBJ);
    }

    /**
     * Set message to 'trash' or 'toDelete'.
     *
     * @param integer $iProfileId User ID
     * @param integer $iMessageId Message ID
     * @param string $sMode Set to this category. Choose between 'trash', 'restor' and 'delete'
     *
     * @throws PH7InvalidArgumentException
     *
     * @return boolean
     */
    public function setTo($iProfileId, $iMessageId, $sMode)
    {
        if (!in_array($sMode, self::MODES)) {
            throw new PH7InvalidArgumentException('Bad set mode: "' . $sMode . '"!');
        }

        $oData = $this->getMsg($iMessageId);
        $sFieldId = ($oData->sender == $iProfileId) ? 'sender' : 'recipient';
        if ($sMode == self::RESTOR_MODE) {
            $sTrashVal = str_replace(array($sFieldId, ','), '', $oData->trash);
        } else {
            $sTrashVal = ($oData->sender == $oData->recipient) ? 'sender,recipient' : $sFieldId . (!empty($oData->trash) ? ',' . $oData->trash : '');
        }
        unset($oData);

        $sField = ($sMode == self::DELETE_MODE) ? 'toDelete' : 'trash';
        $rStmt = Db::getInstance()->prepare('UPDATE' . Db::prefix('Messages') . 'SET ' . $sField . ' = :val WHERE ' . $sFieldId . ' = :profileId AND messageId = :messageId LIMIT 1');
        $rStmt->bindValue(':profileId', $iProfileId, \PDO::PARAM_INT);
        $rStmt->bindValue(':messageId', $iMessageId, \PDO::PARAM_INT);
        $rStmt->bindValue(':val', $sTrashVal, \PDO::PARAM_STR);

        return $rStmt->execute();
    }

    /**
     * @param integer|string $mLooking
     * @param boolean $bCount
     * @param string $sOrderBy
     * @param integer $iSort
     * @param integer $iOffset
     * @param integer $iLimit
     * @param integer|null $iProfileId
     * @param string $sType
     *
     * @return integer|\stdClass
     */
    public function search($mLooking, $bCount, $sOrderBy, $iSort, $iOffset, $iLimit, $iProfileId = null, $sType = 'all')
    {
        $bCount = (bool) $bCount;
        $iOffset = (int) $iOffset;
        $iLimit = (int) $iLimit;
        $mLooking = trim($mLooking);

        $sSqlLimit = (!$bCount) ? ' LIMIT :offset, :limit' : '';
        $sSqlSelect = (!$bCount) ? '*' : 'COUNT(messageId) AS totalMails';
        $sSqlFind = ' ' . (ctype_digit($mLooking) ? '(messageId = :looking)' : '(title LIKE :looking OR message LIKE :looking OR username LIKE :looking OR firstName LIKE :looking OR lastName LIKE :looking)');
        $sSqlOrder = SearchCoreModel::order($sOrderBy, $iSort);

        switch ($sType) {
            case self::INBOX:
                $sSql = 'msg.sender = m.profileId WHERE (msg.recipient = :profileId) AND (NOT FIND_IN_SET(\'recipient\', msg.trash)) AND';
            break;

            case self::OUTBOX:
                $sSql = 'msg.recipient = m.profileId WHERE (msg.sender = :profileId) AND (NOT FIND_IN_SET(\'sender\', msg.toDelete)) AND';
            break;

            case self::TRASH:
                $sSql = 'msg.sender = m.profileId WHERE (msg.recipient = :profileId) AND (FIND_IN_SET(\'recipient\', msg.trash)) AND
                (NOT FIND_IN_SET(\'recipient\', msg.toDelete)) AND';
            break;

            default:
                // All messages
                $sSql = 'msg.sender = m.profileId WHERE ';
        }

        $rStmt = Db::getInstance()->prepare('SELECT ' . $sSqlSelect . ' FROM' . Db::prefix('Messages') . 'AS msg LEFT JOIN ' . Db::prefix('Members') . 'AS m ON ' .
        $sSql . $sSqlFind . $sSqlOrder . $sSqlLimit);

        (ctype_digit($mLooking)) ? $rStmt->bindValue(':looking', $mLooking, \PDO::PARAM_INT) : $rStmt->bindValue(':looking', '%' . $mLooking . '%', \PDO::PARAM_STR);

        if (!empty($iProfileId)) {
            $rStmt->bindParam(':profileId', $iProfileId, \PDO::PARAM_INT);
        }

        if (!$bCount) {
            $rStmt->bindParam(':offset', $iOffset, \PDO::PARAM_INT);
            $rStmt->bindParam(':limit', $iLimit, \PDO::PARAM_INT);
        }

        $rStmt->execute();

        if (!$bCount) {
            $mData = $rStmt->fetchAll(\PDO::FETCH_OBJ);
        } else {
            $oRow = $rStmt->fetch(\PDO::FETCH_OBJ);
            $mData = (int) $oRow->totalMails;
            unset($oRow);
        }

        Db::free($rStmt);

        return $mData;
    }

    /**
     * Check Duplicate Contents.
     *
     * @param integer $iSenderId Sender's ID
     * @param string $sMsg Message content
     *
     * @return boolean Returns TRUE if similar content was found in the table, FALSE otherwise.
     */
    public function isDuplicateContent($iSenderId, $sMsg)
    {
        return SpamModel::detectDuplicate(
            $sMsg,
            'message',
            'sender',
            $iSenderId,
            'Messages',
            'AND NOT FIND_IN_SET(\'recipient\', toDelete)'
        );
    }

    /**
     * To prevent spam!
     *
     * @param integer $iSenderId
     * @param integer $iWaitTime In minutes!
     * @param string $sCurrentTime In date format: 0000-00-00 00:00:00
     *
     * @return boolean Return TRUE if the weather was fine, otherwise FALSE
     */
    public function checkWaitSend($iSenderId, $iWaitTime, $sCurrentTime)
    {
        $rStmt = Db::getInstance()->prepare('SELECT messageId FROM' . Db::prefix('Messages') . 'WHERE sender = :sender AND DATE_ADD(sendDate, INTERVAL :waitTime MINUTE) > :currentTime LIMIT 1');
        $rStmt->bindValue(':sender', $iSenderId, \PDO::PARAM_INT);
        $rStmt->bindValue(':waitTime', $iWaitTime, \PDO::PARAM_INT);
        $rStmt->bindValue(':currentTime', $sCurrentTime, \PDO::PARAM_STR);
        $rStmt->execute();

        return ($rStmt->rowCount() === 0);
    }
}
