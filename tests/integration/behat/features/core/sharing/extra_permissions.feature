Feature: Share extra permissions

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Requesting extra permissions when the advanced feature is disabled returns an error
    When I request the extra permissions for the file
    Then the extra permissions request should fail

  Scenario: Setting extra permissions when the advanced feature is disabled returns an error
    When I set the review permission on a non-existent share
    Then the extra permissions request should fail

  Scenario: Requesting extra permissions for a file with no shares returns an empty list
    Given the advanced feature is enabled
    When I request the extra permissions for the file
    Then the extra permissions should be an empty list

  Scenario: Setting extra permissions on a share persists the permission
    Given the advanced feature is enabled
    And another user exists
    And the file is shared with that user with update permission and without resharing
    When I set the review permission on that share
    Then the extra permissions request should succeed
    And the share should have the review permission set

  Scenario: Setting extra permissions on a public link share with edit permission persists the permission
    Given the advanced feature is enabled
    And the file is shared via a public link with edit permission
    When I set the review permission on that share
    Then the extra permissions request should succeed
    And the share should have the review permission set
