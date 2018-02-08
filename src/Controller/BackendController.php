<?php

namespace Bolt\Extension\TwoKings\IsUseful\Controller;

use Bolt\Controller\Base;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 *
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class BackendController extends Base
{
    /**
     * {@inheritdoc}
     */
    public function addRoutes(ControllerCollection $ctr)
    {
        // General

        $ctr
            ->get('/', [$this, 'indexGet'])
            ->before([$this, 'before'])
            ->bind('is_useful.index')
        ;

        $ctr
            ->get('/{id}', [$this, 'view'])
            ->assert('id', '\d+')
            ->before([$this, 'before'])
            ->bind('is_useful.view')
        ;

        $ctr
            ->get('/unread', [$this, 'unreadGet'])
            ->before([$this, 'before'])
            ->bind('is_useful.unread')
        ;

        // Feedback

        $ctr
            ->get('/delete/{id}', [$this, 'deleteFeedback'])
            ->assert('id', '\d+')
            ->before([$this, 'before'])
            ->bind('is_useful.feedback.delete')
        ;

        $ctr
            ->get('/markasread/{id}', [$this, 'markFeedback'])
            ->assert('id', '\d+')
            ->before([$this, 'before'])
            ->bind('is_useful.feedback.markasread')
        ;

        return $ctr;
    }

    /**
     * Check if the current user is logged in.
     *
     * @param Request     $request
     * @param Application $app
     */
    public function before(Request $request, Application $app)
    {
        $token = $app['session']->get('authentication', false);

        if (! $token) {
            return $this->redirectToRoute('dashboard');
        }
    }

    /**
     *
     *
     * @param Application $app
     * @param Request     $request
     */
    public function indexGet(Application $app, Request $request)
    {
        $stmt = $app['db']->prepare("SELECT * FROM `bolt_is_useful`");
        $stmt->execute();
        $data = $stmt->fetchAll();

        return $this->render('@is_useful/backend/index.twig', [
            'title' => 'Feedback',
            'data'  => $data,
        ], []);
    }

    /**
     *
     */
    public function unreadGet(Application $app, Request $request)
    {
        $stmt = $app['db']->prepare("SELECT * FROM `bolt_is_useful_feedback` WHERE `read` = 0");
        $stmt->execute();
        $feedback = $stmt->fetchAll();

        return $this->render('@is_useful/backend/unread.twig', [
            'title' => 'Unread Feedback',
            'feedback'=> $feedback,
        ], []);
    }

    /**
     *
     * @param Application $app
     * @param Request     $request
     * @param int         $id
     */
    public function view(Application $app, Request $request, $id)
    {
        $stmt = $app['db']->prepare("SELECT * FROM `bolt_is_useful` WHERE `id` = :id");
        $stmt->bindParam('id', $id);
        $stmt->execute();
        $data = $stmt->fetchAll();

        // check iff empty

        $stmt = $app['db']->prepare("SELECT * FROM `bolt_is_useful_feedback` WHERE `is_useful_id` = :id AND `hide` = 0");
        $stmt->bindParam('id', $id);
        $stmt->execute();
        $feedback = $stmt->fetchAll();

        return $this->render('@is_useful/backend/feedback.twig', [
            'title'    => 'Feedback » № ' . $id,
            'data'     => $data,
            'feedback' => $feedback,
        ], []);
    }

    /**
     * Removes feedback by ID
     */
    public function deleteFeedback(Application $app, Request $request, $id)
    {
        // (1) Remove
        $stmt = $app['db']->prepare("UPDATE `bolt_is_useful_feedback` SET `hide` = 1 WHERE `id` = :id");
        $stmt->bindParam('id', $id);
        $stmt->execute();

        // (2) Fetch
        $stmt = $app['db']->prepare("SELECT * FROM `bolt_is_useful_feedback` WHERE `id` = :id");
        $stmt->bindParam('id', $id);
        $stmt->execute();
        $feedback = $stmt->fetch();

        // (3) Get the parent item
        $sql  = "SELECT `bolt_is_useful`.*";
        $sql .= " FROM `bolt_is_useful`";
        $sql .= " JOIN `bolt_is_useful_feedback` ON `bolt_is_useful`.`id` = `bolt_is_useful_feedback`.`is_useful_id`";
        $sql .= " WHERE `bolt_is_useful_feedback`.`id` = :id";

        $stmt = $app['db']->prepare($sql);
        $stmt->bindParam('id', $id);
        $stmt->execute();
        $parent = $stmt->fetch();

        $totals = json_decode($parent['totals']);
        $ips = json_decode($parent['ips']);

        // Warning: this can make data inconsistent!
        if (isset($totals->no)) {
            $totals->no--;
            if ($totals->no < 0) {
                $totals->no = 0;
            }
        }
        unset($ips->{$feedback['ip']});

        $totals = json_encode($totals);
        $ips = json_encode($ips);

        // (4) Set parent item
        $sql  = "UPDATE `bolt_is_useful`";
        $sql .= " SET totals = :totals,";
        $sql .= " ips = :ips";
        $sql .= " WHERE `id` = :id";

        $stmt = $app['db']->prepare($sql);
        $stmt->bindParam('id', $parent['id']);
        $stmt->bindParam('totals', $totals);
        $stmt->bindParam('ips', $ips);
        $stmt->execute();

        return $this->redirect( $request->headers->get('referer') );

        // return $this->redirect();
    }

    /**
     * Mark feedback by ID as read
     */
    public function markFeedback(Application $app, Request $request, $id)
    {
        $stmt = $app['db']->prepare("UPDATE `bolt_is_useful_feedback` SET `read` = 1 WHERE `id` = :id");
        $stmt->bindParam('id', $id);
        $stmt->execute();

        return $this->redirect( $request->headers->get('referer') );
    }
}
