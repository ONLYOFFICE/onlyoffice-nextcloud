Feature: Federation keylock

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Locking a document via an invalid share token returns an error
    When I lock the document via an invalid share token
    Then the key operation should fail

  Scenario: Locking a document via a valid share token succeeds
    Given the file is shared via a link
    When I lock the document via the share token
    Then the key operation should succeed

  Scenario: Unlocking a document via a valid share token succeeds
    Given the file is shared via a link
    When I unlock the document via the share token
    Then the key operation should succeed
