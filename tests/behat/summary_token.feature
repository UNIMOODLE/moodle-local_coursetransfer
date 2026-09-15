@local @local_coursetransfer
Feature: Gestión del token de integración desde la pantalla Resumen
  Para poder conectar este sitio con otras plataformas Moodle,
  como administrador
  necesito crear, regenerar y revocar el token de integración del sitio.

  # Comprueba que la pantalla Resumen carga y muestra el estado de integración
  # y el estado "sin token" cuando todavía no se ha generado ninguno.
  Scenario: La pantalla Resumen muestra el estado de integración y el estado sin token
    Given I log in as "admin"
    And I visit "/local/coursetransfer/index.php"
    Then I should see "Integration status"
    And I should see "There is no token yet"
    And I should see "local_coursetransfer_ws"

  @javascript
  # Recorre el ciclo de vida completo del token desde la interfaz (AMD + servicios
  # web + modal de confirmación): crear desde el estado vacío, regenerar y revocar,
  # volviendo al estado sin token.
  Scenario: Crear, regenerar y revocar el token de integración
    Given I log in as "admin"
    And I visit "/local/coursetransfer/index.php"
    And I should see "There is no token yet"
    # Crear el token desde el estado vacío.
    When I click on "Generate token" "button"
    Then I should see "Regenerate"
    And I should see "Revoke"
    # Regenerarlo (acción destructiva: se confirma en el modal).
    When I click on "Regenerate" "button"
    And I click on "Regenerate" "button" in the ".ct-idx-modal" "css_element"
    Then I should see "Revoke"
    # Revocarlo: el sitio vuelve al estado sin token.
    When I click on "Revoke" "button"
    And I click on "Revoke token" "button"
    Then I should see "There is no token yet"

  # Verifica que un usuario sin permisos de administrador no ve el panel del token.
  Scenario: Un usuario no administrador no ve el panel del token
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And I log in as "teacher1"
    And I visit "/local/coursetransfer/index.php"
    Then I should not see "Integration status"
    And I should not see "There is no token yet"
