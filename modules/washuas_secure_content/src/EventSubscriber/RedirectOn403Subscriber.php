<?php

namespace Drupal\washuas_secure_content\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\permissions_by_term\Service\AccessCheck;

class RedirectOn403Subscriber extends HttpExceptionSubscriberBase {

  /**
   * The account interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * @var \Drupal\permissions_by_term\Service\AccessCheck
   * */
  private $access_check;

  public function __construct(AccountInterface $current_user, MessengerInterface $messenger, ConfigFactoryInterface $config_factory, AccessCheck $access_check) {
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->config = $config_factory->get('washuas_secure_content.settings');
    $this->access_check = $access_check;
  }

  protected function getHandledFormats() {
    return ['html'];
  }

  public function on403(ExceptionEvent $event) {
    // Only run if we've enabled redirection in the module settings
    if ($this->config->get('enable_redirection') == '1') {
      $node = $event->getRequest()->get('node');
      if ($node) {
        // check if the content is tagged as private
        if (!$this->access_check->canUserAccessByNode($node)) {
          $request = $event->getRequest();
          $is_anonymous = $this->currentUser->isAnonymous();
          $route_name = $request->attributes->get('_route');
          $is_not_login = $route_name != 'user.login';

          if ($is_anonymous && $is_not_login) {
            // Show custom access denied message if set.
            if ($this->config->get('redirection_message')) {
              $message = $this->config->get('redirection_message');
              $this->messenger->addError($message);
            }

            $query = $request->query->all();
            $query['destination'] = Url::fromRoute('<current>')->toString();

            $login_uri = Url::fromRoute('user.login', [], ['query' => $query])
              ->toString();

            $returnResponse = new RedirectResponse($login_uri);

            $event->setResponse($returnResponse);
          }
        }
      }
    }
  }

}
