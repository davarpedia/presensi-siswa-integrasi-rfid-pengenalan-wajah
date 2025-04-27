import numpy as np

# Memuat file biner
face_embeddings = np.load('data/embeddings/face_data.npy', allow_pickle=True)

# Melihat data yang dimuat
print(face_embeddings)
