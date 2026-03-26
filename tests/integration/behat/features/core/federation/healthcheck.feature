Feature: Federation healthcheck

  Scenario: The healthcheck endpoint reports the service is alive
    When I request the federation healthcheck
    Then the healthcheck should report the service is alive
