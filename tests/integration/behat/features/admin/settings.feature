Feature: Admin Settings

  Background:
    Given I am logged in as admin

  Scenario: Saving valid address settings persists the configuration
    When I save valid address settings
    Then the settings should be saved successfully
    And the settings should reflect the changes

  Scenario: Saving an unreachable document server address reports an error
    When I save invalid address settings
    Then the settings should report a connection error

  Scenario: Saving common settings succeeds
    When I save common settings
    Then the common settings should be saved successfully

  Scenario: Saving security settings succeeds
    When I save security settings
    Then the security settings should be saved successfully

  Scenario: Clearing version history succeeds
    When I clear the version history
    Then the version history should be cleared successfully
