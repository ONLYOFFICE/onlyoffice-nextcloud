Feature: Editor get file version

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Requesting version data for the current version returns version info
    When I request version 1 of the file
    Then the version data should be retrieved successfully
    And the version data should contain a file type and download URL

  Scenario: Requesting a later version than the history length returns the current version's data
    Given I have updated the file content
    When I request version 99 of the file
    Then the version data should be retrieved successfully

  Scenario: Requesting version data for a non-existent file returns an error
    When I request version 1 of a file that does not exist
    Then the version request should fail
