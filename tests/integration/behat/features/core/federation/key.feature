Feature: Federation get key

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Requesting the document key via an invalid share token returns an error
    When I request the document key via an invalid share token
    Then the key response should contain an error

  Scenario: Requesting the document key via a valid share token returns a key
    Given the file is shared via a link
    When I request the document key via the share token
    Then the key response should contain a document key
