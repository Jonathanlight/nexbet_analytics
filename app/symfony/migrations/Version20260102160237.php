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
        $this->addSql('CREATE TABLE basketball_matches (id SERIAL NOT NULL, home_team_id INT NOT NULL, away_team_id INT NOT NULL, league VARCHAR(255) NOT NULL, match_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(50) NOT NULL, home_score_q1 INT DEFAULT NULL, away_score_q1 INT DEFAULT NULL, home_score_q2 INT DEFAULT NULL, away_score_q2 INT DEFAULT NULL, home_score_q3 INT DEFAULT NULL, away_score_q3 INT DEFAULT NULL, home_score_q4 INT DEFAULT NULL, away_score_q4 INT DEFAULT NULL, home_score_ot INT DEFAULT NULL, away_score_ot INT DEFAULT NULL, home_final_score INT DEFAULT NULL, away_final_score INT DEFAULT NULL, home_stats JSON DEFAULT NULL, away_stats JSON DEFAULT NULL, head_to_head_stats JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5AE3E5FC9C4C13F6 ON basketball_matches (home_team_id)');
        $this->addSql('CREATE INDEX IDX_5AE3E5FC45185D02 ON basketball_matches (away_team_id)');
        $this->addSql('CREATE INDEX idx_bb_match_date ON basketball_matches (match_date)');
        $this->addSql('CREATE INDEX idx_bb_match_league ON basketball_matches (league)');
        $this->addSql('COMMENT ON COLUMN basketball_matches.match_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN basketball_matches.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN basketball_matches.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE matches (id SERIAL NOT NULL, home_team_id INT NOT NULL, away_team_id INT NOT NULL, league VARCHAR(255) NOT NULL, match_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(50) NOT NULL, home_score INT DEFAULT NULL, away_score INT DEFAULT NULL, home_score_ht INT DEFAULT NULL, away_score_ht INT DEFAULT NULL, referee VARCHAR(255) DEFAULT NULL, stadium VARCHAR(255) DEFAULT NULL, weather_conditions JSON DEFAULT NULL, head_to_head_stats JSON DEFAULT NULL, home_corners INT DEFAULT NULL, away_corners INT DEFAULT NULL, home_yellow_cards INT DEFAULT NULL, away_yellow_cards INT DEFAULT NULL, home_red_cards INT DEFAULT NULL, away_red_cards INT DEFAULT NULL, home_xg DOUBLE PRECISION DEFAULT NULL, away_xg DOUBLE PRECISION DEFAULT NULL, home_lineup JSON DEFAULT NULL, away_lineup JSON DEFAULT NULL, events JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_62615BA9C4C13F6 ON matches (home_team_id)');
        $this->addSql('CREATE INDEX IDX_62615BA45185D02 ON matches (away_team_id)');
        $this->addSql('CREATE INDEX idx_match_date ON matches (match_date)');
        $this->addSql('CREATE INDEX idx_match_league ON matches (league)');
        $this->addSql('CREATE INDEX idx_match_status ON matches (status)');
        $this->addSql('COMMENT ON COLUMN matches.match_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN matches.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN matches.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE odds (id SERIAL NOT NULL, match_id INT NOT NULL, bookmaker VARCHAR(100) NOT NULL, bet_type VARCHAR(100) NOT NULL, market VARCHAR(100) NOT NULL, odds DOUBLE PRECISION NOT NULL, implied_probability DOUBLE PRECISION DEFAULT NULL, margin DOUBLE PRECISION DEFAULT NULL, is_best_odds BOOLEAN NOT NULL, fetched_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_odds_match ON odds (match_id)');
        $this->addSql('CREATE INDEX idx_odds_bookmaker ON odds (bookmaker)');
        $this->addSql('CREATE INDEX idx_odds_bet_type ON odds (bet_type)');
        $this->addSql('COMMENT ON COLUMN odds.fetched_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN odds.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN odds.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE players (id SERIAL NOT NULL, team_id INT NOT NULL, name VARCHAR(255) NOT NULL, position VARCHAR(50) NOT NULL, jersey_number INT DEFAULT NULL, statistics JSON NOT NULL, goals_scored INT DEFAULT NULL, assists INT DEFAULT NULL, yellow_cards INT DEFAULT NULL, red_cards INT DEFAULT NULL, matches_played INT DEFAULT NULL, avg_rating DOUBLE PRECISION DEFAULT NULL, x_g DOUBLE PRECISION DEFAULT NULL, x_a DOUBLE PRECISION DEFAULT NULL, scoring_probability DOUBLE PRECISION DEFAULT NULL, is_injured BOOLEAN NOT NULL, is_suspended BOOLEAN NOT NULL, photo_url VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_player_team ON players (team_id)');
        $this->addSql('CREATE INDEX idx_player_position ON players (position)');
        $this->addSql('COMMENT ON COLUMN players.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN players.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE predictions (id SERIAL NOT NULL, match_id INT NOT NULL, bet_type VARCHAR(100) NOT NULL, prediction VARCHAR(255) NOT NULL, probability DOUBLE PRECISION NOT NULL, confidence DOUBLE PRECISION NOT NULL, confidence_level VARCHAR(50) NOT NULL, calculation_details JSON NOT NULL, expected_value DOUBLE PRECISION DEFAULT NULL, kelly_percentage DOUBLE PRECISION DEFAULT NULL, is_value_bet BOOLEAN NOT NULL, is_safe_bet BOOLEAN NOT NULL, result VARCHAR(50) DEFAULT NULL, algorithm_scores JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_prediction_match ON predictions (match_id)');
        $this->addSql('CREATE INDEX idx_prediction_bet_type ON predictions (bet_type)');
        $this->addSql('CREATE INDEX idx_prediction_confidence ON predictions (confidence_level)');
        $this->addSql('COMMENT ON COLUMN predictions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN predictions.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE teams (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, sport VARCHAR(10) NOT NULL, league VARCHAR(255) NOT NULL, country VARCHAR(100) DEFAULT NULL, elo_rating DOUBLE PRECISION DEFAULT NULL, statistics JSON DEFAULT NULL, form_last5 JSON DEFAULT NULL, position INT DEFAULT NULL, matches_played INT DEFAULT NULL, wins INT DEFAULT NULL, draws INT DEFAULT NULL, losses INT DEFAULT NULL, goals_for INT DEFAULT NULL, goals_against INT DEFAULT NULL, points INT DEFAULT NULL, logo_url VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_team_sport ON teams (sport)');
        $this->addSql('CREATE INDEX idx_team_league ON teams (league)');
        $this->addSql('COMMENT ON COLUMN teams.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN teams.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE users (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, betting_profile VARCHAR(50) NOT NULL, bankroll DOUBLE PRECISION NOT NULL, initial_bankroll DOUBLE PRECISION NOT NULL, max_stake_percentage DOUBLE PRECISION DEFAULT NULL, betting_preferences JSON DEFAULT NULL, statistics JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON users (email)');
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE basketball_matches ADD CONSTRAINT FK_5AE3E5FC9C4C13F6 FOREIGN KEY (home_team_id) REFERENCES teams (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE basketball_matches ADD CONSTRAINT FK_5AE3E5FC45185D02 FOREIGN KEY (away_team_id) REFERENCES teams (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA9C4C13F6 FOREIGN KEY (home_team_id) REFERENCES teams (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BA45185D02 FOREIGN KEY (away_team_id) REFERENCES teams (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE odds ADD CONSTRAINT FK_C542D5902ABEACD6 FOREIGN KEY (match_id) REFERENCES matches (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT FK_264E43A6296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE predictions ADD CONSTRAINT FK_8E87BCE62ABEACD6 FOREIGN KEY (match_id) REFERENCES matches (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE basketball_matches DROP CONSTRAINT FK_5AE3E5FC9C4C13F6');
        $this->addSql('ALTER TABLE basketball_matches DROP CONSTRAINT FK_5AE3E5FC45185D02');
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BA9C4C13F6');
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BA45185D02');
        $this->addSql('ALTER TABLE odds DROP CONSTRAINT FK_C542D5902ABEACD6');
        $this->addSql('ALTER TABLE players DROP CONSTRAINT FK_264E43A6296CD8AE');
        $this->addSql('ALTER TABLE predictions DROP CONSTRAINT FK_8E87BCE62ABEACD6');
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
