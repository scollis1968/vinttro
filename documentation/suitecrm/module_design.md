# VIsP SuiteCRM Module Design


To help simplify the deveopment and deployment we are adopting a single package "visp" that will contain all the custom modules.

Bespoke Modules required :-

1. Member
    * This would be a 1 to 1 link to a contact record.
    * This is where we can hold a details of the member like when they became member and a breif profile of them, this could be a useful reminder when in conversation, what football team, where they live, partners name, gender preference.
1. fleet
1. vehicle
1. vehicle_check
1. vehicle_issue
1. vehicle_service
1. vehicle_repair
1. ins_motor_policy
1. ins_motor_vehicle_cover
1. ins_motor_claim
1. ins_home_policy
1. ins_home_asset_cover
1. ins_home_claim

---
title : Fleet - module relationships
---

``` mermaid
erDiagram
    visp_fleet              ||--|{ visp_vehicle : has
    visp_fleet              ||--|{ visp_fleet_memberships : has
    visp_driver             ||--|| visp_driver_incedent : has
    visp_driver             ||--|| Contacts : link
    visp_fleet_memberships  ||--|| Contacts : link
    visp_vehicle            ||--|{ visp_vehicle_check : contains
    visp_vehicle            ||--|{ visp_vehicle_issue : contains
    visp_vehicle_check      ||--|{ visp_vehicle_issue : contains
    visp_vehicle            ||--|{ visp_vehicle_work : contains
    visp_vehicle_issue      ||--|| visp_vehicle_work : contains
    Client                  ||--|{ visp_fleet : contains

    visp_fleet {
        int id
        string name
    }

    visp_fleet_memberships {
        int id
        string name
    }
    
    visp_vehicle {
        int id
        string name
    }
    
    visp_driver {
        int id
        string name
    }

    visp_driver_incedent {
        int id
        string name
        select type
        int points
    }
    
    visp_vehicle_issue {
        int id
        string reg
        select type
    }
    
    visp_vehicle_work {
        int id
        string reg
        select type
    }
    
    visp_vehicle_check {
        int id
        string reg
    }
    
    Contacts {
        int id
        string name
    }
    
    Client {
        int id
        string name
    }

```

## visp_vehicle_issue
- Type
  1. 

## visp_vehicle_work
Supplier, Terms (supplier + Type), Payment, Commission,  

- Types
  1. Service
  1. MOT
  1. Maintenance
  1. Repair
  1. Tyers

- Stage
  1. Estimate
  1. In progress
  1. Completed  
