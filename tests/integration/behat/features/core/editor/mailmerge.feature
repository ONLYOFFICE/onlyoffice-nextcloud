Feature: Mail merge email address list

  Background:
    Given I am logged in as a regular user

  Scenario: Returns an emails key in the response
    When I request the email address list
    Then the email list response should succeed
    And the response should contain an emails key

  Scenario: Returns an empty array when the user has no mail accounts configured
    When I request the email address list
    Then the email list response should succeed
    And the emails array should be empty
