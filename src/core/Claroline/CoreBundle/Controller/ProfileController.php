<?php

namespace Claroline\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Claroline\CoreBundle\Form\ProfileType;

/**
 * Controller of the user profile.
 */
class ProfileController extends Controller
{
    /**
     * Displays an editable form of the current user's profile.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function formAction()
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $form = $this->createForm(new ProfileType($user->getOwnedRoles()), $user);

        return $this->render(
            'ClarolineCoreBundle:Profile:profile_form.html.twig',
            array('profile_form' => $form->createView()));
    }

    /**
     * Updates the user's profile and redirects to the profile form.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function updateAction()
    {
        $request = $this->get('request');
        $user = $this->get('security.context')->getToken()->getUser();
        $form = $this->get('form.factory')->create(new ProfileType($user->getOwnedRoles()), $user);
        $form->bindRequest($request);

        if ($form->isValid()) {
            $user = $form->getData();
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($user);
            $em->flush();
            $this->get('security.context')->getToken()->setUser($user);

            return $this->redirect($this->generateUrl('claro_profile_form'));
        }

        return $this->render(
            'ClarolineCoreBundle:Profile:profile_form.html.twig',
            array('profile_form' => $form->createView()));
    }

    /**
     * Displays the public profile of an user.
     *
     * @param integer $userId The id of the user we want to see the profile
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($userId)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $user = $em->getRepository('ClarolineCoreBundle:User')->find($userId);

        return $this->render(
            'ClarolineCoreBundle:Profile:profile.html.twig',
            array('user' => $user)
        );
    }
}