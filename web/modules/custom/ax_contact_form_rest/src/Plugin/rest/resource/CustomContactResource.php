<?php

namespace Drupal\ax_contact_form_rest\Plugin\rest\resource;

use Drupal\user\UserData;
use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\contact\Entity\Message;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Component\Serialization\Json;
use Drupal\contact\MailHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a custom endpoints for contact form submit from post API endpoint.
 *
 * RESTful API post endpoint for submitting data through the personal contact form,
 * provides integration for front-end applications with Drupal backend.
 *
 * @RestResource(
 *   id = "custom_contact_resource",
 *   label = @Translation("Custom Contact Form Resource"),
 *   uri_paths = {
 *     "create" = "/api/contact-user"
 *   }
 * )
 */
class CustomContactResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The contact mail handler service.
   *
   * @var \Drupal\contact\MailHandlerInterface
   */
  protected $mailHandler;

  /**
   * The currently authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserData
   */
  protected $userData;

  /**
   * Constructs a CustomContactResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\contact\MailHandlerInterface $mail_handler
   *   The contact mail handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The currently authenticated user.
   * @param \Drupal\user\UserData $user_data
   *   The user data service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, MailHandlerInterface $mail_handler, AccountInterface $current_user, UserData $user_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->mailHandler = $mail_handler;
    $this->currentUser = $current_user;
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('contact.mail_handler'),
      $container->get('current_user'),
      $container->get('user.data'),
    );
  }

  /**
   * Create a contact form submission from an post endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *  When user is not exist or data are empty.
   */
  public function post(Request $request) {
    $contact_data = Json::decode($request->getContent());

    // Check if recipient, subject and message are missing in the input data.
    if (empty($contact_data['recipient']) || empty($contact_data['subject']) || empty($contact_data['message'])) {
      throw new BadRequestHttpException('Recipient, subject, or message details are missing.');
    }

    // Check if the recipient exists.
    $recipient = $this->entityTypeManager->getStorage('user')->load($contact_data['recipient']);
    if (!$recipient) {
      // If the recipient does not exist, respond with a Bad Request error.
      throw new BadRequestHttpException('Recipient does not exist. Please provide a valid recipient(user) ID.');
    }

    // Check if the recipient has enabled the option to be contacted.
    $user_data = $this->userData->get('contact', $contact_data['recipient'], 'enabled');
    if ($user_data != '1') {
      throw new BadRequestHttpException('The provided recipient has disabled the option to be contacted. Please contact the administrator.');
    }

    // Create the message entity using the contact form.
    $message = Message::create([
      'contact_form' => 'personal',
      'subject' => $contact_data['subject'],
      'message' => $contact_data['message'],
      'copy' => isset($contact_data['copy']) ? 1 : 0,
      'recipient' => $contact_data['recipient'],
      'name' => $this->currentUser->getAccountName(),
      'mail' => $this->currentUser->getEmail(),
    ]);
    $message->save();

    try {
      // Send the email message.
      $this->mailHandler->sendMailMessages($message, $this->currentUser);
    }
    catch (\Exception $e) {
      // Log the error with recipient information.
      $this->logger->error($this->t('Failed to send email to "%recipient".', ['%recipient' => $contact_data['recipient']]));
      // Respond with a 500 Internal Server Error and a more informative error message.
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
    }

    $response = ['message' => 'Your contact form has been successfully submitted.'];
    return new ResourceResponse($response);
  }

}
