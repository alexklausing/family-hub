import json
import random

base_words = [
    {"en": "Apple", "fr": "Pomme", "es": "Manzana"},
    {"en": "Sun", "fr": "Soleil", "es": "Sol"},
    {"en": "Moon", "fr": "Lune", "es": "Luna"},
    {"en": "Star", "fr": "Étoile", "es": "Estrella"},
    {"en": "Water", "fr": "Eau", "es": "Agua"},
    {"en": "Fire", "fr": "Feu", "es": "Fuego"},
    {"en": "Tree", "fr": "Arbre", "es": "Árbol"},
    {"en": "Book", "fr": "Livre", "es": "Libro"},
    {"en": "Cat", "fr": "Chat", "es": "Gato"},
    {"en": "Dog", "fr": "Chien", "es": "Perro"},
    {"en": "Bird", "fr": "Oiseau", "es": "Pájaro"},
    {"en": "Fish", "fr": "Poisson", "es": "Pez"},
    {"en": "House", "fr": "Maison", "es": "Casa"},
    {"en": "Car", "fr": "Voiture", "es": "Coche"},
    {"en": "Friend", "fr": "Ami", "es": "Amigo"},
    {"en": "Happy", "fr": "Heureux", "es": "Feliz"},
    {"en": "Smile", "fr": "Sourire", "es": "Sonrisa"},
    {"en": "Hello", "fr": "Bonjour", "es": "Hola"},
    {"en": "Thank you", "fr": "Merci", "es": "Gracias"},
    {"en": "Please", "fr": "S'il vous plaît", "es": "Por favor"},
    {"en": "Yes", "fr": "Oui", "es": "Sí"},
    {"en": "No", "fr": "Non", "es": "No"},
    {"en": "Good", "fr": "Bon", "es": "Bueno"},
    {"en": "Beautiful", "fr": "Beau", "es": "Hermoso"},
    {"en": "Love", "fr": "Amour", "es": "Amor"},
    {"en": "Time", "fr": "Temps", "es": "Tiempo"},
    {"en": "Day", "fr": "Jour", "es": "Día"},
    {"en": "Night", "fr": "Nuit", "es": "Noche"},
    {"en": "Morning", "fr": "Matin", "es": "Mañana"},
    {"en": "Sky", "fr": "Ciel", "es": "Cielo"},
    {"en": "Earth", "fr": "Terre", "es": "Tierra"}
]

months = {
    1: 31, 2: 29, 3: 31, 4: 30, 5: 31, 6: 30,
    7: 31, 8: 31, 9: 30, 10: 31, 11: 30, 12: 31
}

words = {}
random.seed(42)

for m in range(1, 13):
    words[str(m)] = {}
    for d in range(1, months[m] + 1):
        words[str(m)][str(d)] = random.choice(base_words)

with open("storage/app/words_of_the_day.json", "w") as f:
    json.dump(words, f)

print("Generated words_of_the_day.json")
