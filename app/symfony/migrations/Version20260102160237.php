<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260102160237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE basketball_matches (id INT AUTO_INCREMENT NOT NULL, home_team_id INT NOT NULL, away_team_id INT NOT NULL, league VARCHAR(255) NOT NULL, match_date DATETIME NOT NULL, status VARCHAR(50) NOT NULL, home_score_q1 INT DEFAULT NULL, away_score_q1 INT DEFAULT NULL, home_score_q2 INT DEFAULT NULL, away_score_q2 INT DEFAULT NULL, home_score_q3 INT DEFAULT NULL, away_score_q3 INT DEFAULT NULL, home_score_q4 INT DEFAULT NULL, away_score_q4 INT DEFAULT NULL, home_score_ot INT DEFAULT NULL, away_score_ot INT DEFAULT NULL, home_final_score INT DEFAULT NULL, away_final_score INT DEFAULT NULL, home_stats JSON DEFAULT NULL, away_stats JSON DEFAULT NULL, head_to_head_stats JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_5AE3E5FC9C4C13F6 (home_team_id), INDEX IDX_5AE3E5FC45185D02 (away_team_id), INDEX idx_bb_match_date (match_date), INDEX idx_bb_match_league (league), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE matches (id INT AUTO_INCREMENT NOT NULL, home_team_id INT NOT NULL, away_team_id INT NOT NULL, league VARCHAR(255) NOT NULL, match_date DATETIME NOT NULL, status VARCHAR(50) NOT NULL, home_score INT DEFAULT NULL, away_score INT DEFAULT NULL, home_score_ht INT DEFAULT NULL, away_score_ht INT DEFAULT NULL, referee VARCHAR(255) DEFAULT NULL, stadium VARCHAR(255) DEFAULT NULL, weather_conditions JSON DEFAULT NULL, head_to_head_stats JSON DEFAULT NULL, home_corners INT DEFAULT NULL, away_corners INT DEFAULT NULL, home_yellow_cards INT DEFAULT NULL, away_yellow_cards INT DEFAULT NULL, home_red_cards INT DEFAULT NULL, away_red_cards INT DEFAULT NULL, home_xg DOUBLE DEFAULT NULL, away_xg DOUBLE DEFAULT NULL, home_lineup JSON DEFAULT NULL, away_lineup JSON DEFAULT NULL, events JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_62615BA9C4C13F6 (home_team_id), INDEX IDX_62615BA45185D02 (away_team_id), INDEX idx_match_date (match_date), INDEX idx_match_league (league), INDEX idx_match_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE odds (id INT AUTO_INCREMENT NOT NULL, match_id INT NOT NULL, bookmaker VARCHAR(100) NOT NULL, bet_type VARCHAR(100) NOT NULL, market VARCHAR(100) NOT NULL, odds DOUBLE NOT NULL, implied_probability DOUBLE DEFAULT NULL, margin DOUBLE DEFAULT NULL, is_best_odds TINYINT(1) NOT NULL, fetched_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_odds_match (match_id), INDEX idx_odds_bookmaker (bookmaker), INDEX idx_odds_bet_type (bet_type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE players (id INT AUTO_INCREMENT NOT NULL, team_id INT NOT NULL, name VARCHAR(255) NOT NULL, position VARCHAR(50) NOT NULL, jersey_number INT DEFAULT NULL, statistics JSON NOT NULL, goals_scored INT DEFAULT NULL, assists INT DEFAULT NULL, yellow_cards INT DEFAULT NULL, red_cards INT DEFAULT NULL, matches_played INT DEFAULT NULL, avg_rating DOUBLE DEFAULT NULL, x_g DOUBLE DEFAULT NULL, x_a DOUBLE DEFAULT NULL, scoring_probability DOUBLE DEFAULT NULL, is_injured TINYINT(1) NOT NULL, is_suspended TINYINT(1) NOT NULL, photo_url VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_player_team (team_id), INDEX idx_player_position (position), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE predictions (id INT AUTO_INCREMENT NOT NULL, match_id INT NOT NULL, bet_type VARCHAR(100) NOT NULL, prediction VARCHAR(255) NOT NULL, probability DOUBLE NOT NULL, confidence DOUBLE NOT NULL, confidence_level VARCHAR(50) NOT NULL, calculation_details JSON NOT NULL, expected_value DOUBLE DEFAULT NULL, kelly_percentage DOUBLE DEFAULT NULL, is_value_bet TINYINT(1) NOT NULL, is_safe_bet TINYINT(1) NOT NULL, result VARCHAR(50) DEFAULT NULL, algorithm_scores JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_prediction_match (match_id), INDEX idx_prediction_bet_type (bet_type), INDEX idx_prediction_confidence (confidence_level), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE teams (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, sport VARCHAR(10) NOT NULL, league VARCHAR(255) NOT NULL, country VARCHAR(100) DEFAULT NULL, elo_rating DOUBLE DEFAULT NULL, statistics JSON DEFAULT NULL, form_last5 JSON DEFAULT NULL, position INT DEFAULT NULL, matches_played INT DEFAULT NULL, wins INT DEFAULT NULL, draws INT DEFAULT NULL, losses INT DEFAULT NULL, goals_for INT DEFAULT NULL, goals_against INT DEFAULT NULL, points INT DEFAULT NULL, logo_url VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_team_sport (sport), INDEX idx_team_league (league), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, betting_profile VARCHAR(50) NOT NULL, bankroll DOUBLE NOT NULL, initial_bankroll DOUBLE NOT NULL, max_stake_percentage DOUBLE DEFAULT NULL, betting_preferences JSON DEFAULT NULL, statistics JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE basketball_matches ADD CONSTRAINT FK_5AE3E5FC9C4C13F6 FOREIGN KEY (home_team_id) REFERENCES teams (id)');
        $this->addSql('ALTER TABLE basketball_matches ADD CONSTRAINT FK_5AE3E5FC45185D02 FOREIGN KEY (away_team_id) REFERENCES teams (id)');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA9C4C13F6 FOREIGN KEY (home_team_id) REFERENCES teams (id)');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA45185D02 FOREIGN KEY (away_team_id) REFERENCES teams (id)');
        $this->addSql('ALTER TABLE odds ADD CONSTRAINT FK_C542D5902ABEACD6 FOREIGN KEY (match_id) REFERENCES matches (id)');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT FK_264E43A6296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id)');
        $this->addSql('ALTER TABLE predictions ADD CONSTRAINT FK_8E87BCE62ABEACD6 FOREIGN KEY (match_id) REFERENCES matches (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE basketball_matches DROP FOREIGN KEY FK_5AE3E5FC9C4C13F6');
        $this->addSql('ALTER TABLE basketball_matches DROP FOREIGN KEY FK_5AE3E5FC45185D02');
        $this->addSql('ALTER TABLE matches DROP FOREIGN KEY FK_62615BA9C4C13F6');
        $this->addSql('ALTER TABLE matches DROP FOREIGN KEY FK_62615BA45185D02');
        $this->addSql('ALTER TABLE odds DROP FOREIGN KEY FK_C542D5902ABEACD6');
        $this->addSql('ALTER TABLE players DROP FOREIGN KEY FK_264E43A6296CD8AE');
        $this->addSql('ALTER TABLE predictions DROP FOREIGN KEY FK_8E87BCE62ABEACD6');
        $this->addSql('DROP TABLE basketball_matches');
        $this->addSql('DROP TABLE matches');
        $this->addSql('DROP TABLE odds');
        $this->addSql('DROP TABLE players');
        $this->addSql('DROP TABLE predictions');
        $this->addSql('DROP TABLE teams');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
