terraform {
  required_providers {
    google = {
      source = "hashicorp/google"
      version = ">= 4.0"
    }
  }
}

provider "google" {
  project      = "suitecrm-wordpress"
  region       = "your-preferred-region"
  credentials  = file("path/to/your/service-account-key.json")
}