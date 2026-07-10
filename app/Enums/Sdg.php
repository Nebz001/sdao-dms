<?php

namespace App\Enums;

/**
 * The 17 UN Sustainable Development Goals. Backs the "SDG" field on the
 * Activity Calendar (Phase 2 item 7 slice 1) and is reused as-is for the
 * Activity Proposal's "Target SDG" field in a later slice.
 */
enum Sdg: string
{
    case NoPoverty = 'no_poverty';
    case ZeroHunger = 'zero_hunger';
    case GoodHealthAndWellBeing = 'good_health_and_well_being';
    case QualityEducation = 'quality_education';
    case GenderEquality = 'gender_equality';
    case CleanWaterAndSanitation = 'clean_water_and_sanitation';
    case AffordableAndCleanEnergy = 'affordable_and_clean_energy';
    case DecentWorkAndEconomicGrowth = 'decent_work_and_economic_growth';
    case IndustryInnovationAndInfrastructure = 'industry_innovation_and_infrastructure';
    case ReducedInequalities = 'reduced_inequalities';
    case SustainableCitiesAndCommunities = 'sustainable_cities_and_communities';
    case ResponsibleConsumptionAndProduction = 'responsible_consumption_and_production';
    case ClimateAction = 'climate_action';
    case LifeBelowWater = 'life_below_water';
    case LifeOnLand = 'life_on_land';
    case PeaceJusticeAndStrongInstitutions = 'peace_justice_and_strong_institutions';
    case PartnershipsForTheGoals = 'partnerships_for_the_goals';

    public function label(): string
    {
        return match ($this) {
            self::NoPoverty => 'No Poverty',
            self::ZeroHunger => 'Zero Hunger',
            self::GoodHealthAndWellBeing => 'Good Health and Well-being',
            self::QualityEducation => 'Quality Education',
            self::GenderEquality => 'Gender Equality',
            self::CleanWaterAndSanitation => 'Clean Water and Sanitation',
            self::AffordableAndCleanEnergy => 'Affordable and Clean Energy',
            self::DecentWorkAndEconomicGrowth => 'Decent Work and Economic Growth',
            self::IndustryInnovationAndInfrastructure => 'Industry, Innovation and Infrastructure',
            self::ReducedInequalities => 'Reduced Inequalities',
            self::SustainableCitiesAndCommunities => 'Sustainable Cities and Communities',
            self::ResponsibleConsumptionAndProduction => 'Responsible Consumption and Production',
            self::ClimateAction => 'Climate Action',
            self::LifeBelowWater => 'Life Below Water',
            self::LifeOnLand => 'Life on Land',
            self::PeaceJusticeAndStrongInstitutions => 'Peace, Justice and Strong Institutions',
            self::PartnershipsForTheGoals => 'Partnerships for the Goals',
        };
    }

    /** @return int 1-17, the goal's official number. */
    public function number(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}
